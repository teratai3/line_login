(() => {
  document.addEventListener('DOMContentLoaded', () => {
    const lineLoginButton = document.querySelector('.line-login-button');
    const editSubmitButton = document.getElementById('edit-submit');

    const usernameField = document.getElementById('edit-name');
    const passwordField = document.getElementById('edit-pass');

    if (lineLoginButton) {
      lineLoginButton.addEventListener('click', (e) => {
        // 各フィールドから必須属性を削除
        if (usernameField) {
          usernameField.removeAttribute('required');
        }
        if (passwordField) {
          passwordField.removeAttribute('required');
        }
      });
    }

    if (editSubmitButton) {
      editSubmitButton.addEventListener('click', (e) => {
        // 各フィールドに必須属性を再度追加
        if (usernameField) {
          usernameField.setAttribute('required', 'required');
        }
        if (passwordField) {
          passwordField.setAttribute('required', 'required');
        }
      });
    }
  });
})();