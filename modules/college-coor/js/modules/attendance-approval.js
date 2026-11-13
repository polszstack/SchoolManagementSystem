const endpoint = 'controllers/AttendanceReviewController.php';
const labels = {pending:'Pending approval', approved:'Approved', rejected:'Rejected'};
const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
function photo(path, label) {
    // Monitoring stores only validated, generated upload paths.
    if (!/^uploads\/(?:face_to_face|meeting_screenshots)\/[a-zA-Z0-9_.-]+$/.test(path || '')) return '';
    const url = `../monitoring/public/${path}`;
    return `<a href="${escape(url)}" target="_blank" rel="noopener"><img class="review-photo" src="${escape(url)}" alt="${escape(label)}"></a>`;
}
function initAttendanceReview() {
    const root = document.querySelector('#attendance-review');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = 'true';
    let page = 1, request = 0;
    const filter = root.querySelector('select');
    const message = root.querySelector('[data-review-message]');
    const records = root.querySelector('[data-review-records]');
    const previous = root.querySelector('[data-review-previous]');
    const next = root.querySelector('[data-review-next]');
    async function load() {
        const current = ++request;
        message.textContent = 'Loading attendance…';
        records.replaceChildren(); previous.disabled = next.disabled = true;
        try {
            const response = await fetch(`${endpoint}?filter=${encodeURIComponent(filter.value)}&page=${page}`, {credentials:'same-origin', cache:'no-store', headers:{'X-Requested-With':'XMLHttpRequest'}});
            const result = await response.json();
            if (current !== request || !root.isConnected) return;
            if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load attendance.');
            message.textContent = result.records.length ? `${result.records.length} submissions on this page` : 'No attendance submissions for this filter.';
            root.querySelector('[data-review-page]').textContent = `Page ${page}`;
            previous.disabled = page === 1; next.disabled = !result.has_more;
            for (const record of result.records) {
                const card = document.createElement('article'); card.className = 'review-card';
                const state = labels[record.approval_status] ? record.approval_status : 'pending';
                const detail = (title, value) => `<div><dt>${escape(title)}</dt><dd>${escape(value || '—')}</dd></div>`;
                const signature = /^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(record.faculty_signature || '')
                    ? `<img class="review-signature" src="${escape(record.faculty_signature)}" alt="Teacher e-signature for ${escape(record.faculty_name)}"><p>Signed: ${escape(record.signature_signed_at)} (Asia/Manila) · Captured by staff ID: ${escape(record.signature_captured_by)}</p>`
                    : '<p>Not signed — no teacher e-signature stored.</p>';
                card.innerHTML = `<h2>${escape(record.faculty_name)} · ${escape(record.attendance_date)}</h2>
                    <span class="review-state ${state}">${labels[state]}</span> <small>${record.source === 'archive' ? 'Archived' : 'Active'} submission</small>
                    <dl class="review-details">${detail('Section',record.course_section)}${detail('Subject / room',`${record.subject_code} / ${record.room}`)}${detail('Recorded mark',record.status)}${detail('Final mark',record.final_status || 'Not final')}${detail('Students',String(record.student_count ?? 0))}${detail('Recorded by',record.verified_by)}${detail('Check time (Asia/Manila)',record.check_time)}${detail('Verification',record.verification_method)}</dl>
                    <p>Staff remarks: ${escape(record.remarks || 'None')}</p><p>Meeting link: ${escape(record.meeting_link || 'None')}</p>
                    <details><summary>Teacher e-signature and evidence</summary>${signature}${photo(record.face_to_face_image,'Classroom photo')}${photo(record.meeting_screenshot,'Online meeting screenshot')}</details>
                    ${state === 'pending' ? `<form data-custom-submit><label>Review remarks (required when rejecting)<textarea name="remarks" maxlength="4000"></textarea></label><button class="review-approve" type="submit" value="approved">Approve attendance</button><button class="review-reject" type="submit" value="rejected">Reject attendance</button><p role="alert"></p></form>` : `<p>Reviewed by ${escape(record.reviewed_by_name || record.reviewed_by)} · ${escape(record.reviewed_at)} (Asia/Manila)</p><p>Review remarks: ${escape(record.review_remarks || 'None')}</p>`}`;
                const form = card.querySelector('form');
                if (form) form.addEventListener('submit', async event => {
                    event.preventDefault();
                    const decision = event.submitter?.value;
                    if (!['approved','rejected'].includes(decision) || form.dataset.busy) return;
                    const remarks = form.elements.remarks.value.trim();
                    const error = form.querySelector('[role=alert]'); error.textContent = '';
                    if (decision === 'rejected' && !remarks) { error.textContent = 'Enter a reason for rejecting this attendance.'; form.elements.remarks.focus(); return; }
                    const body = new FormData(form);
                    Object.entries({source:record.source,id:record.id,version:record.approval_version,decision,remarks,csrf:root.dataset.csrf}).forEach(([key,value]) => body.set(key,value));
                    form.dataset.busy = 'true'; form.querySelectorAll('button').forEach(button => button.disabled = true);
                    try {
                        const response = await fetch(endpoint,{method:'POST',body,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
                        const result = await response.json();
                        if (!response.ok || !result.success) throw new Error(result.message || 'Unable to save decision.');
                        await load();
                    } catch (failure) { error.textContent = failure.message; }
                    finally { delete form.dataset.busy; form.querySelectorAll('button').forEach(button => button.disabled = false); }
                });
                records.append(card);
            }
        } catch (failure) { if (current === request) message.textContent = failure.message; }
    }
    filter.addEventListener('change', () => { page = 1; load(); });
    root.querySelector('[data-review-refresh]').addEventListener('click',load);
    previous.addEventListener('click',()=>{ page--; load(); });
    next.addEventListener('click',()=>{ page++; load(); });
    load();
}
document.addEventListener('DOMContentLoaded',initAttendanceReview);
window.addEventListener('page:loaded',initAttendanceReview);
initAttendanceReview();
