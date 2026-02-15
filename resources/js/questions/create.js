document.addEventListener('DOMContentLoaded', () => {
  const typeSelect = document.getElementById('question_type');
  const root = document.getElementById('type-specific-fields');

  if (!typeSelect || !root) return;

  //parse the json safely
  function safeJsonParse(value){
      try {
      return JSON.parse(value);
      }catch(e){
          return value;
      }
  }

  //normalize the json values
    function normalizeOptions(raw){
      if(raw == null) return [];

      if(typeof raw === 'string') return safeJsonParse(raw);

      if(Array.isArray(raw)) return raw;

      if(typeof raw === 'object') {
          const letters = Object.keys(raw).sort();
          return letters.map((k) => raw[k]);
      }

      return [];
    }

    function normalizeCorrect(raw){
      if(raw == null) return [];

      if(typeof raw === 'string') {
          raw = safeJsonParse(raw);
      }

      if(Array.isArray(raw)) return raw;

      return [String(raw)];
    }

  const oldOptions = normalizeOptions(safeJsonParse(root.dataset.oldOptions || '[]'));
  const oldCorrect = normalizeCorrect(safeJsonParse(root.dataset.oldCorrect || '[]'));

  const state = {
      ype: typeSelect.value || root.dataset.questionType || '',
      optionCount: Math.max(4, oldOptions.length || 0),
  };

  function clearRoot() {
    root.innerHTML = '';
  }

  function mountTemplate(templateId) {
    const tpl = document.getElementById(templateId);
    if (!tpl) return null;
    const node = tpl.content.cloneNode(true);
    root.appendChild(node);
    return root.firstElementChild;
  }

  function letter(i) {
    return String.fromCharCode(65 + i);
  }

  function renderMcq() {
    clearRoot();
    const ui = mountTemplate('tpl-mcq');
    if (!ui) return;

    const isMultiple = state.type === 'mcq_multiple';
    const optionsContainer = ui.querySelector('#options-container');
    const correctContainer = ui.querySelector('#correct-answers-container');
    const correctLabel = ui.querySelector('#mcq-correct-label');

    correctLabel.textContent = isMultiple
      ? 'Correct Answers (Select Multiple) *'
      : 'Correct Answer (Select One) *';

    function renderOptions() {
      optionsContainer.innerHTML = '';
      for (let i = 0; i < state.optionCount; i++) {
        const L = letter(i);
        const value = oldOptions[i] ?? '';

        optionsContainer.insertAdjacentHTML('beforeend', `
          <div class="row mb-2 option-row" data-index="${i}">
            <div class="col-md-1"><strong>${L}</strong></div>
            <div class="col-md-10">
              <input type="text" class="form-control"
                     name="options[]"
                     placeholder="Option ${L}"
                     value="${escapeHtml(value)}">
            </div>
          </div>
        `);
      }
    }

    function renderCorrect() {
      correctContainer.innerHTML = '';
      const inputType = isMultiple ? 'checkbox' : 'radio';

      for (let i = 0; i < state.optionCount; i++) {
        const L = letter(i);
        const checked = oldCorrect.includes(L) ? 'checked' : '';

        correctContainer.insertAdjacentHTML('beforeend', `
          <div class="col-md-2 mb-2">
            <div class="form-check">
              <input class="form-check-input" type="${inputType}"
                     name="correct_answers[]"
                     value="${L}" id="correct${L}" ${checked}>
              <label class="form-check-label" for="correct${L}">${L}</label>
            </div>
          </div>
        `);
      }
    }

    renderOptions();
    renderCorrect();
  }

  function renderTrueFalse() {
    clearRoot();
    const ui = mountTemplate('tpl-true-false');
    if (!ui) return;

    // restore old checked
    if (oldCorrect.includes('true')) ui.querySelector('#correctTrue')?.setAttribute('checked', 'checked');
    if (oldCorrect.includes('false')) ui.querySelector('#correctFalse')?.setAttribute('checked', 'checked');
  }

  function renderFillBlank() {
    clearRoot();
    const ui = mountTemplate('tpl-fill-blank');
    if (!ui) return;

    const container = ui.querySelector('#fill-blank-answers');

    const initial = oldCorrect.length ? oldCorrect : [''];
    const limited = initial.slice(0, 3);

    container.innerHTML = '';
    limited.forEach((val, idx) => {
      container.insertAdjacentHTML('beforeend', blankRowHtml(val, idx > 0));
    });
  }

  function blankRowHtml(value, removable) {
    return `
      <div class="row mb-2 answer-row">
        <div class="col-md-8">
          <input type="text" class="form-control"
                 name="correct_answers[]"
                 placeholder="Enter correct answer"
                 value="${escapeHtml(value ?? '')}">
        </div>
        <div class="col-md-2">
          ${removable ? `<button type="button" class="btn btn-sm btn-danger" data-action="remove-blank-answer">Remove</button>` : ''}
        </div>
      </div>
    `;
  }

  // Event delegation for all dynamic buttons
  root.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;

    const action = btn.dataset.action;

    if (action === 'add-option') {
      if (state.optionCount >= 6) return;
      state.optionCount++;
      renderMcq();
    }

    if (action === 'remove-option') {
      if (state.optionCount <= 2) return;
      state.optionCount--;
      renderMcq();
    }

    if (action === 'add-blank-answer') {
      const ui = root.querySelector('[data-type-ui="fill_blank"]');
      const answers = ui?.querySelectorAll('.answer-row')?.length ?? 0;
      if (answers >= 3) return;

      ui?.querySelector('#fill-blank-answers')
        ?.insertAdjacentHTML('beforeend', blankRowHtml('', true));
    }

    if (action === 'remove-blank-answer') {
      btn.closest('.answer-row')?.remove();
    }
  });

  function render() {
    state.type = typeSelect.value || root.dataset.questionType || '';
    if (state.type === 'mcq_single' || state.type === 'mcq_multiple') return renderMcq();
    if (state.type === 'true_false') return renderTrueFalse();
    if (state.type === 'fill_blank') return renderFillBlank();
    clearRoot();
  }

  // Simple HTML escaping for values inserted into HTML
  function escapeHtml(str) {
    return String(str)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  typeSelect.addEventListener('change', render);
  render();

    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
