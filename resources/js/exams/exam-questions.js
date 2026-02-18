function safeJsonParse(value, fallback = null) {
    try {
        return JSON.parse(value);
    } catch {
        return fallback;
    }
}

function escapeHtml(str) {
    return String(str)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function getPageRoot() {
    return document.getElementById('exam-questions-page');
}

function readConfig(root) {
    const raw = root?.dataset?.config;
    const cfg = safeJsonParse(raw, {});
    return cfg || {};
}

function readAllQuestions() {
    const el = document.getElementById('all-questions-json');
    if (!el) return [];
    return safeJsonParse(el.textContent, []) || [];
}

function endpointReplace(url, params) {
    let out = url;
    Object.entries(params).forEach(([key, value]) => {
        out = out.replace(`{${key}}`, encodeURIComponent(String(value)));
    });
    return out;
}

function createApi({ csrf }) {
    async function request(url, { method = 'GET', json = null } = {}) {
        const res = await fetch(url, {
            method,
            headers: {
                'Accept': 'application/json',
                ...(json ? { 'Content-Type': 'application/json' } : {}),
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: json ? JSON.stringify(json) : null,
        });

        // Check if the response is a redirect (302)
        if (res.redirected) {
            // Follow the redirect manually and return the final URL
            return { redirected: true, url: res.url };
        }

        const contentType = res.headers.get('content-type') || '';
        const isJson = contentType.includes('application/json');

        if (!res.ok) {
            const body = isJson ? await res.json().catch(() => ({})) : await res.text().catch(() => '');
            throw new Error(body?.message || `Request failed (${res.status})`);
        }

        return isJson ? res.json() : res.text();
    }

    return { request };
}

function initSortable({ root, api, config }) {
    const container = root.querySelector('#questions-container');
    if (!container) return;

    if (!window.Sortable) {
        // Sortable should be imported in app.js and assigned to window.Sortable
        // Fail silently to avoid breaking the page.
        return;
    }

    new window.Sortable(container, {
        animation: 150,
        handle: '.drag-handle',
        onEnd: async () => {
            const items = Array.from(root.querySelectorAll('.question-item'));
            const questions = items.map((item, index) => ({
                id: item.dataset.questionId,
                order: index + 1,
            }));

            try {
                await api.request(config.endpoints.reorder, { method: 'POST', json: { questions } });

                // update badges
                items.forEach((item, idx) => {
                    const badge = item.querySelector('.order-badge');
                    if (badge) badge.textContent = String(idx + 1);
                });

                showTemporaryMessage('Question order updated', 'success');
            } catch (e) {
                console.error(e);
                showTemporaryMessage('Failed to update order', 'danger');
            }
        },
    });
}

function initModals() {
    const previewEl = document.getElementById('questionPreviewModal');
    const removeEl = document.getElementById('removeQuestionModal');

    return {
        preview: previewEl && window.bootstrap ? new window.bootstrap.Modal(previewEl) : null,
        remove: removeEl && window.bootstrap ? new window.bootstrap.Modal(removeEl) : null,
    };
}

function renderBulkList({ root, items }) {
    const bulkSelection = root.querySelector('#bulkSelection');
    if (!bulkSelection) return;

    if (items.length === 0) {
        bulkSelection.innerHTML = '<div class="text-center text-muted py-3">No questions found</div>';
        return;
    }

    bulkSelection.innerHTML = items
        .map((q) => {
            return `
        <div class="form-check py-1">
          <input class="form-check-input bulk-checkbox"
                 type="checkbox"
                 value="${escapeHtml(q.id)}"
                 id="q${escapeHtml(q.id)}">
          <label class="form-check-label" for="q${escapeHtml(q.id)}">
            ${escapeHtml(q.text.length > 60 ? q.text.slice(0, 60) + '…' : q.text)}
            <small class="text-muted">(${escapeHtml(q.points)} pts)</small>
          </label>
        </div>
      `;
        })
        .join('');
}

function updateBulkAddButton(root) {
    const bulkAddBtn = root.querySelector('#bulkAddBtn');
    if (!bulkAddBtn) return;

    const checked = root.querySelectorAll('.bulk-checkbox:checked').length;
    bulkAddBtn.disabled = checked === 0;
    bulkAddBtn.innerHTML = `<i class="bi bi-plus-circle"></i> Add Selected (${checked})`;
}

function showTemporaryMessage(message, type = 'success') {
    // Remove any existing temporary messages
    const existing = document.querySelector('.temporary-message');
    if (existing) existing.remove();

    const alertDiv = document.createElement('div');
    alertDiv.className = `temporary-message alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alertDiv.style.zIndex = '9999';
    alertDiv.style.maxWidth = '400px';
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2 fs-5"></i>
            <div class="flex-grow-1">${message}</div>
            <button type="button" class="btn-close ms-2" onclick="this.closest('.alert').remove()"></button>
        </div>
    `;

    document.body.appendChild(alertDiv);

    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

function initHandlers({ root, api, config, allQuestions, modals }) {
    // Live search for visible "available questions" list (DOM-only)
    const searchInput = root.querySelector('#questionSearch');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const term = searchInput.value.toLowerCase();
            root.querySelectorAll('.available-question').forEach((card) => {
                const txt = (card.querySelector('.question-text')?.textContent || '').toLowerCase();
                card.style.display = txt.includes(term) ? '' : 'none';
            });
        });
    }

    // Bulk search: filter from JSON, render checkboxes (no Blade in JS)
    const bulkSearch = root.querySelector('#bulkSearch');
    const clearBulkSearch = root.querySelector('#clearBulkSearch');

    if (bulkSearch) {
        const performBulkSearch = () => {
            const term = bulkSearch.value.toLowerCase().trim();
            const filtered = term === ''
                ? allQuestions
                : allQuestions.filter((q) => q.text.toLowerCase().includes(term));

            renderBulkList({ root, items: filtered });
            updateBulkAddButton(root);
        };

        bulkSearch.addEventListener('input', performBulkSearch);

        // Clear button
        if (clearBulkSearch) {
            clearBulkSearch.addEventListener('click', () => {
                bulkSearch.value = '';
                performBulkSearch();
            });
        }

        // initial render (first 50 questions)
        renderBulkList({ root, items: allQuestions.slice(0, 50) });
        updateBulkAddButton(root);
    }

    // Event delegation for click/change actions
    root.addEventListener('click', async (e) => {
        const viewBtn = e.target.closest('.view-question');
        if (viewBtn) {
            const questionText = viewBtn.dataset.questionText || '';
            const questionType = viewBtn.dataset.questionType || '';
            const options = safeJsonParse(viewBtn.dataset.options || '{}', {});
            const correctAnswers = safeJsonParse(viewBtn.dataset.correctAnswers || '[]', []);

            const textEl = document.getElementById('previewQuestionText');
            const optionsEl = document.getElementById('previewOptions');
            const correctEl = document.getElementById('previewCorrectAnswers');

            if (textEl) textEl.textContent = questionText;

            let optionsHtml = '';
            if (questionType.includes('mcq')) {
                optionsHtml = '<h6>Options:</h6><ul class="list-unstyled ms-3">';
                Object.entries(options || {}).forEach(([letter, text]) => {
                    optionsHtml += `<li><strong>${escapeHtml(letter)}:</strong> ${escapeHtml(text)}</li>`;
                });
                optionsHtml += '</ul>';
            } else if (questionType === 'true_false') {
                optionsHtml = '<h6>Options:</h6><ul class="list-unstyled ms-3"><li>True</li><li>False</li></ul>';
            }
            if (optionsEl) optionsEl.innerHTML = optionsHtml;
            if (correctEl) correctEl.textContent = (correctAnswers || []).join(', ');

            modals.preview?.show();
            return;
        }

        const removeBtn = e.target.closest('.remove-question');
        if (removeBtn) {
            const questionId = removeBtn.dataset.questionId;
            const questionText = removeBtn.dataset.questionText || '';

            const textEl = document.getElementById('removeQuestionText');
            if (textEl) textEl.textContent = questionText;

            const form = document.getElementById('removeQuestionForm');
            if (form) {
                form.action = endpointReplace(config.endpoints.detach, { question: questionId });

                // Store questionId for the submit handler
                form.dataset.questionId = questionId;
            }

            modals.remove?.show();
            return;
        }

        const addBtn = e.target.closest('.add-question');
        if (addBtn) {
            const questionId = addBtn.dataset.questionId;

            // Show loading state
            addBtn.disabled = true;
            addBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Adding...';

            try {
                // For single add, we'll use a form submission to handle redirect properly
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = config.endpoints.attachOne;
                form.style.display = 'none';

                const csrfInput = document.createElement('input');
                csrfInput.name = '_token';
                csrfInput.value = config.csrf;

                const questionInput = document.createElement('input');
                questionInput.name = 'question_id';
                questionInput.value = questionId;

                form.appendChild(csrfInput);
                form.appendChild(questionInput);
                document.body.appendChild(form);
                form.submit();
            } catch (err) {
                console.error(err);
                showTemporaryMessage('Failed to add question', 'danger');
                addBtn.disabled = false;
                addBtn.innerHTML = '<i class="bi bi-plus-circle"></i> Add';
            }
            return;
        }

        const bulkAddBtn = e.target.closest('#bulkAddBtn');
        if (bulkAddBtn) {
            const ids = Array.from(root.querySelectorAll('.bulk-checkbox:checked')).map((cb) => cb.value);

            if (!ids.length) return;

            // Show loading state
            bulkAddBtn.disabled = true;
            bulkAddBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Adding...';

            try {
                // For bulk add, we'll use a form submission to handle redirect properly
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = config.endpoints.attachBulk;
                form.style.display = 'none';

                const csrfInput = document.createElement('input');
                csrfInput.name = '_token';
                csrfInput.value = config.csrf;
                form.appendChild(csrfInput);

                ids.forEach(id => {
                    const input = document.createElement('input');
                    input.name = 'question_ids[]';
                    input.value = id;
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
            } catch (err) {
                console.error(err);
                showTemporaryMessage('Failed to add questions', 'danger');
                bulkAddBtn.disabled = false;
                bulkAddBtn.innerHTML = `<i class="bi bi-plus-circle"></i> Add Selected (${ids.length})`;
            }
            return;
        }
    });

    // Remove form submit handler
    const removeForm = document.getElementById('removeQuestionForm');
    if (removeForm) {
        removeForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const questionId = removeForm.dataset.questionId;
            if (!questionId) return;

            const url = endpointReplace(config.endpoints.detach, { question: questionId });

            // Show loading
            const submitBtn = removeForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Removing...';

            try {
                const response = await api.request(url, { method: 'DELETE' });

                // Check if we got a redirect response
                if (response && response.redirected) {
                    window.location.href = response.url;
                } else {
                    window.location.reload();
                }
            } catch (err) {
                console.error(err);
                showTemporaryMessage('Failed to remove question', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }

    // Points update (event delegation with debounce)
    let pointsTimeout;
    root.addEventListener('input', (e) => {
        const input = e.target.closest('.points-input');
        if (!input) return;

        clearTimeout(pointsTimeout);

        pointsTimeout = setTimeout(async () => {
            const questionId = input.dataset.questionId;
            const points = Number(input.value);

            // Validate
            if (points < 1 || points > 10) {
                input.classList.add('is-invalid');
                return;
            } else {
                input.classList.remove('is-invalid');
            }

            const url = endpointReplace(config.endpoints.points, { question: questionId });

            try {
                const data = await api.request(url, { method: 'PUT', json: { points } });

                const badge = document.getElementById('total-marks-badge');
                if (badge && data?.total_marks != null) {
                    badge.textContent = `Total Marks: ${data.total_marks}`;
                }

                // Update override indicator
                const originalPoints = input.dataset.original;
                const overrideSpan = input.closest('.col-md-2').querySelector('.text-warning');

                if (points != originalPoints) {
                    if (!overrideSpan) {
                        const newSpan = document.createElement('small');
                        newSpan.className = 'text-warning d-block';
                        newSpan.textContent = `Orig: ${originalPoints}`;
                        input.closest('.col-md-2').appendChild(newSpan);
                    }
                } else {
                    if (overrideSpan) {
                        overrideSpan.remove();
                    }
                }
            } catch (err) {
                console.error(err);
                showTemporaryMessage('Failed to update points', 'danger');
            }
        }, 500);
    });

    // Bulk checkbox state (event delegation)
    root.addEventListener('change', (e) => {
        if (e.target.closest('.bulk-checkbox')) {
            updateBulkAddButton(root);

            // Update select all button
            const selectAllBtn = root.querySelector('#selectAllBtn');
            if (selectAllBtn) {
                const allCheckboxes = root.querySelectorAll('.bulk-checkbox');
                const allChecked = allCheckboxes.length > 0 &&
                    Array.from(allCheckboxes).every(cb => cb.checked);

                selectAllBtn.innerHTML = allChecked ?
                    '<i class="bi bi-x"></i>' :
                    '<i class="bi bi-check-all"></i>';
                selectAllBtn.title = allChecked ? 'Deselect All' : 'Select All';
            }
        }
    });

    // Select/Deselect all
    const selectAllBtn = root.querySelector('#selectAllBtn');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', () => {
            const checkboxes = root.querySelectorAll('.bulk-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);

            checkboxes.forEach(cb => cb.checked = !allChecked);
            updateBulkAddButton(root);
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const root = getPageRoot();
    if (!root) return;

    const config = readConfig(root);
    const allQuestions = readAllQuestions();
    const api = createApi({ csrf: config.csrf });

    const modals = initModals();

    initSortable({ root, api, config });
    initHandlers({ root, api, config, allQuestions, modals });
});
