function login_Password(inputId, icon) {
    const passwordInput = document.getElementById(inputId); 
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.textContent = "🙉";
    } else {
        passwordInput.type = "password";
        icon.textContent = "🙈";
    }
}