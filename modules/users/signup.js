document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('signupForm');
  const password = document.getElementById('password');
  const confirmPassword = document.getElementById('confirmPassword');
  const dob = document.getElementById('dob');

  form.addEventListener('submit', function (e) {
    // Confirm password
    if (password.value !== confirmPassword.value) {
      e.preventDefault();
      alert("Passwords do not match!");
      confirmPassword.focus();
      return;
    }

    // DOB validation
    const today = new Date();
    const birthDate = new Date(dob.value);

    if (birthDate > today) {
      e.preventDefault();
      alert("Date of birth cannot be in the future.");
      return;
    }

    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }

    if (age < 15) {
      e.preventDefault();
      alert("You must be at least 15 years old to sign up.");
      return;
    }
  });
});

// Toggle Password Visibility
function togglePassword(inputId, iconElement) {
  const input = document.getElementById(inputId);
  if (input.type === "password") {
    input.type = "text";
    iconElement.textContent = "👁";
  } else {
    input.type = "password";
    iconElement.textContent = "🙈";
  }
}
