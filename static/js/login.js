var needsValidation = document.querySelectorAll('.needs-validation')

function sendData(form){
    const data = new FormData(form);
    fetch('/checklogin', {
        method: "POST",
        body: data
    })
    .then(req => req.json())
    .then(res => {
        location.href = `/perfil/${data.get('username')}`;
    })
}

Array.prototype.slice.call(needsValidation)
.forEach(function(form) {
form.addEventListener('submit', function(event) {
    event.preventDefault()
    if (!form.checkValidity()) {
        event.stopPropagation()
    }else {
        sendData(form)
    }

    form.classList.add('was-validated')
}, false)
})