document.getElementById('showPassword').addEventListener('click', function() {
    const passwordField = document.getElementById('password');
    
    if (this.checked) {
        passwordField.type = 'text';  // Mengubah tipe input menjadi text sehingga password terlihat
    } else {
        passwordField.type = 'password';  // Mengubah kembali ke tipe password sehingga tersembunyi
    }
});