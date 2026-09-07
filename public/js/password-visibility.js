document.querySelectorAll('[data-password-toggle]').forEach((button) => {
  const targetId = button.dataset.passwordTarget;

  if (!targetId) {
    return;
  }

  const input = document.getElementById(targetId);

  if (!(input instanceof HTMLInputElement)) {
    return;
  }

  button.addEventListener('click', () => {
    const shouldShowPassword = input.type === 'password';

    input.type = shouldShowPassword
      ? 'text'
      : 'password';

    button.setAttribute(
      'aria-pressed',
      shouldShowPassword ? 'true' : 'false'
    );

    const label = shouldShowPassword
      ? 'Masquer le mot de passe'
      : 'Afficher le mot de passe';

    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);

    const icon = button.querySelector('i');

    if (icon) {
      icon.classList.toggle(
        'fa-eye',
        !shouldShowPassword
      );

      icon.classList.toggle(
        'fa-eye-slash',
        shouldShowPassword
      );
    }
  });
});
