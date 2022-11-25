window.profile = function () {
    const formEditInfo = document.getElementById("formEditInfo");
    const formChangePass = document.getElementById("formChangePass");
    const btnUploadFtPerfil = document.getElementById("btnFtPerfil")
    const inputFtPerfil = document.getElementById("ftPerfil")
    const previewFtPerfil = document.getElementById("previewFtPerfil");

    const validAndParseData = function (data){
        const parseData = new FormData();
        for(let [key, valor] of data) {
            valor = valor ?? valor.trim();
            if(!valor) return false;
            console.log(valor);
            parseData.append(key, valor);
        };
        return parseData;
    }


    btnUploadFtPerfil.addEventListener('click', async e => {
        const { value: file } = await Swal.fire({
            title: 'Seleccione una imagen',
            input: 'file',
            inputAttributes: {
            'accept': 'image/*',
            'aria-label': 'Suba su imagen de perfil'
            }
        })
        
        if (file) {
            const reader = new FileReader()
            reader.onload = (e) => {
                const filebs64 = e.target.result;
                Swal.fire({
                    title: 'Su imagen se ha subido',
                    imageUrl: filebs64,
                    imageAlt: 'The uploaded picture'
                })
                previewFtPerfil.src = filebs64;          
            }

            const dt = new DataTransfer()
            dt.items.add(file);
            inputFtPerfil.files = dt.files;

            reader.readAsDataURL(file)
        }
    })

    formEditInfo.addEventListener('submit', (e)=>{
        e.preventDefault();
        let data = new FormData(formEditInfo)
        data = validAndParseData(data);

        if(!data) return Swal.fire({
            title: "Datos erroneos",
            text: "verifique los datos ingresados"
        });
        fetch('/update/infoUser', {
            method: "POST",
            body: data
        })
        .then(req => req.json())
        .then(res => {
            if (res.status === null) return;
            else if(res.status) location.reload()
            else Swal.fire({
                icon: 'error',
                title: 'Ha ocurrido un error',
                text: 'No se ha podido actualizar los datos'
            });
        })
    })

    formChangePass.addEventListener('submit', e => {
        e.preventDefault();
        let data = new FormData(formChangePass)
        data = validAndParseData(data);
        if(!data) Swal.fire({
            icon: 'error',
            title: 'Datos erroneos',
            text: 'Verifique los datos ingresados'
        })

        fetch('/changepassword', {
            method: "POST",
            body: data
        })
        .then(req => req.json())
        .then(res => {
            if(!res.status){
                return Swal.fire({
                    icon: 'error',
                    text: res.error
                })
            }
            Swal.fire({
                icon: 'success',
                title: 'Contraseña actualizada',
                text: 'Se ha cambaido su contraseña correctamene'
            })
        })
    })
}
window.profile();