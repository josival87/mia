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
    const sync = () => {
      const type = form.querySelector('input[name="type"]:checked')?.value;
      Array.from(select.options).forEach(option => {
        option.hidden = option.dataset.kind && option.dataset.kind !== type;
        if (option.selected && option.hidden) select.value = '';
      });
    };
    form.querySelectorAll('input[name="type"]').forEach(input => input.addEventListener('change', sync));
    sync();
  });

  const goalForm = document.querySelector('[data-goal-form]');
  document.querySelectorAll('[data-goal-edit]').forEach(button => button.addEventListener('click', () => {
    if (!goalForm) return;
    goalForm.querySelector('[name="category_id"]').value = button.dataset.categoryId;
    goalForm.querySelector('[name="monthly_amount"]').value = button.dataset.monthlyAmount;
  }));

  setTimeout(() => document.querySelectorAll('[data-alert]').forEach(alert => alert.classList.add('fade')), 5500);
});
