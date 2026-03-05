/**
 * Project File Purpose:
 * - auth.js
 * - Handles client-side interactions and lightweight UI behavior.
 */
// Validates basic email structure on client side before submit.
function isEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// Performs client-side registration form validation checks.
function validateRegister(form) {
  const username = form.username.value.trim();
  const email = form.email.value.trim();
  const mobileNumber = form.mobile_number.value.trim();
  const password = form.password.value;
  const confirmPassword = form.confirm_password.value;

  if (username.length < 3) {
    alert("Username must be at least 3 characters.");
    return false;
  }
  if (!isEmail(email)) {
    alert("Enter a valid email address.");
    return false;
  }
  if (!/^\+?[0-9]{10,15}$/.test(mobileNumber)) {
    alert("Enter a valid mobile number (10 to 15 digits, optional + at start).");
    return false;
  }
  if (password.length < 6) {
    alert("Password must be at least 6 characters.");
    return false;
  }
  if (password !== confirmPassword) {
    alert("Password and confirm password do not match.");
    return false;
  }
  return true;
}

// Performs client-side login form validation checks.
function validateLogin(form) {
  const email = form.email.value.trim();
  const password = form.password.value;

  if (!isEmail(email)) {
    alert("Enter a valid email address.");
    return false;
  }
  if (password.length < 6) {
    alert("Password must be at least 6 characters.");
    return false;
  }
  return true;
}

// Toggles password field visibility for better UX.
function togglePassword(inputId, buttonEl) {
  const input = document.getElementById(inputId);
  if (!input) return;

  const isHidden = input.type === "password";
  input.type = isHidden ? "text" : "password";
  buttonEl.textContent = isHidden ? "Hide" : "Show";
}

