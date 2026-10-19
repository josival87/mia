document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.querySelector('.sidebar');
  document.querySelector('[data-menu-toggle]')?.addEventListener('click', () => sidebar?.classList.toggle('open'));
  document.addEventListener('click', (event) => {
    if (sidebar?.classList.contains('open') && !sidebar.contains(event.target) && !event.target.closest('[data-menu-toggle]')) sidebar.classList.remove('open');
  });

  document.querySelectorAll('[data-password-toggle]').forEach(button => button.addEventListener('click', () => {
    const input = button.parentElement.querySelector('[data-password]');
    input.type = input.type === 'password' ? 'text' : 'password';
    button.textContent = input.type === 'password' ? 'Mostrar' : 'Ocultar';
  }));

  const openModal = id => {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    setTimeout(() => modal.querySelector('input,select,textarea')?.focus(), 50);
  };
  const closeModal = modal => {
    modal?.classList.remove('open');
    modal?.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  };
  document.querySelectorAll('[data-modal-open]').forEach(button => button.addEventListener('click', () => openModal(button.dataset.modalOpen)));
  document.querySelectorAll('[data-modal-close]').forEach(button => button.addEventListener('click', () => closeModal(button.closest('.modal'))));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeModal(document.querySelector('.modal.open')); });
  if (document.querySelector('.modal.open')) document.body.style.overflow = 'hidden';

  document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  }));
  document.querySelectorAll('[data-scroll-to]').forEach(button => button.addEventListener('click', () => document.querySelector(button.dataset.scrollTo)?.scrollIntoView({behavior:'smooth'})));

  document.querySelectorAll('[data-mask="cpf"]').forEach(input => input.addEventListener('input', () => {
    const value = input.value.replace(/\D/g, '').slice(0,11);
    input.value = value.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  }));

  document.querySelectorAll('[data-category-form]').forEach(form => {
    const select = form.querySelector('select[name="category_id"]');
    const options = Array.from(select.options);
    const sync = () => {
      const type = form.querySelector('input[name="type"]:checked')?.value;
      const selectedValue = select.value;
      const matchingOptions = options.filter(option => !option.dataset.kind || option.dataset.kind === type);
      select.replaceChildren(...matchingOptions);
      select.value = matchingOptions.some(option => option.value === selectedValue) ? selectedValue : '';
    };
    form.querySelectorAll('input[name="type"]').forEach(input => input.addEventListener('change', sync));
    sync();
  });

  document.querySelectorAll('[data-card-purchase-form]').forEach(form => {
    const fields = form.querySelector('[data-card-purchase-fields]');
    const panel = form.querySelector('[data-card-payment-panel]');
    const paymentMethod = form.querySelector('[name="payment_method"]');
    const purchaseType = form.querySelector('[name="card_purchase_type"]');
    const installmentsField = form.querySelector('[data-installments-field]');
    const syncCardFields = () => {
      const isExpense = form.querySelector('[name="type"]:checked')?.value === 'expense';
      fields.hidden = !isExpense;
      if (!isExpense) paymentMethod.value = 'direct';
      const isCard = isExpense && paymentMethod.value === 'credit_card';
      panel.hidden = !isCard;
      panel.querySelectorAll('select,input').forEach(input => { input.disabled = !isCard; });
      if (purchaseType && isCard) {
        const isInstallments = purchaseType.value === 'installments';
        installmentsField.hidden = !isInstallments;
        installmentsField.querySelector('input').disabled = !isInstallments;
      }
    };
    form.querySelectorAll('[name="type"], [name="payment_method"], [name="card_purchase_type"]').forEach(input => input.addEventListener('change', syncCardFields));
    syncCardFields();
  });

  const goalForm = document.querySelector('[data-goal-form]');
  document.querySelectorAll('[data-goal-edit]').forEach(button => button.addEventListener('click', () => {
    if (!goalForm) return;
    goalForm.querySelector('[name="category_id"]').value = button.dataset.categoryId;
    goalForm.querySelector('[name="monthly_amount"]').value = button.dataset.monthlyAmount;
  }));

  document.querySelectorAll('[data-upload-form]').forEach(form => form.addEventListener('submit', () => {
    const button = form.querySelector('[data-upload-submit]');
    if (!button) return;
    button.disabled = true;
    button.textContent = 'A Mia está lendo a fatura…';
  }));

  const invoiceReview = document.querySelector('[data-invoice-review]');
  if (invoiceReview) {
    const checkboxes = Array.from(invoiceReview.querySelectorAll('[data-invoice-item]'));
    const count = invoiceReview.querySelector('[data-selected-summary]');
    const total = invoiceReview.querySelector('[data-selected-total]');
    const submit = invoiceReview.querySelector('[data-import-submit]');
    const currency = new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL'});
    const updateInvoiceSummary = () => {
      const selected = checkboxes.filter(checkbox => checkbox.checked);
      count.textContent = `${selected.length} selecionado${selected.length === 1 ? '' : 's'}`;
      total.textContent = currency.format(selected.reduce((sum, checkbox) => sum + Number(checkbox.dataset.amount || 0), 0));
      submit.disabled = selected.length === 0;
      invoiceReview.querySelectorAll('[data-duplicate-confirm]').forEach(confirmation => {
        const item = checkboxes.find(checkbox => checkbox.value === confirmation.dataset.duplicateConfirm);
        confirmation.disabled = !item?.checked;
        confirmation.required = Boolean(item?.checked);
        if (!item?.checked) confirmation.checked = false;
      });
    };
    checkboxes.forEach(checkbox => checkbox.addEventListener('change', updateInvoiceSummary));
    invoiceReview.querySelectorAll('[data-invoice-select]').forEach(button => button.addEventListener('click', () => {
      checkboxes.forEach(checkbox => { checkbox.checked = button.dataset.invoiceSelect === 'all'; });
      updateInvoiceSummary();
    }));
    invoiceReview.addEventListener('submit', () => {
      submit.disabled = true;
      submit.textContent = 'Importando…';
    });
    updateInvoiceSummary();
  }

  setTimeout(() => document.querySelectorAll('[data-alert]').forEach(alert => alert.classList.add('fade')), 5500);
});
