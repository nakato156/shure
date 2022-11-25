window.storage = function () {
    function isImg(filename){
        const ext = filename.split('.').slice(-1)[0];
        return ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext);
    }

    const start = function (){
        const files = document.getElementsByClassName("myfiles");
        for(element of files) {
            element.addEventListener('click', actionsFile)
        };
    }

    function downloadFile(ruta){
        const link = document.createElement('a');
        link.href = `/api/v1/download?file=${ruta}`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function deleteFile(ruta){
        fetch(`/api/v1/delete`,{
            method: "DELETE",
            body: JSON.stringify({
                file: ruta
            })
        })
        .then(req => req.json())
        .then(res =>{
            if(!res.status){
                return Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se ha podido eliminar el archivo'
                })
            }
            const element = document.getElementById("ruta");
            const path = element.lastElementChild.getAttribute("path");
            const username = element.firstElementChild.nextElementSibling.getAttribute("user");
            document.querySelector('button[data-bs-dismiss="modal"]').click();
            getContenidoDir(path, username)
        })
    }

    const saveContentPath = function (path, content){
        content["date"] = new Date();
        localStorage.setItem(path, JSON.stringify(content));
    }

    const showCache = function (path){
        const { username, files, date } = JSON.parse(localStorage.getItem(path));
        
        let diffTime = new Date() - (new Date(date)).getTime();
        if( Math.round( diffTime / (1000*60) ) > 2 ) return getContenidoDir(path, username);
        showContent(files, path, username);
    }

    const cd = function (e) {
        const path = e.target.getAttribute("path");
        if(path == "/") return getContenidoDir(path, e.target.getAttribute("user"));
        showCache(path);
    }

    const changeBreadCumPath = function (path){
        const ruta = document.getElementById("ruta");
        const cant_rutas = ruta.childElementCount;
        const listaRutas = ruta.children;
        const dirs = path.split("/")

        
        if(listaRutas[cant_rutas - 1].getAttribute("path") == path) return;
        if(cant_rutas == dirs.length){
            listaRutas[cant_rutas - 1].classList.remove("active");
            ruta.innerHTML+= `<li class="breadcrumb-item active active-path" path="${path}">${dirs.slice(-1)[0]}</li>`;
        }else {
            temp = "";
            for(ruta_ of listaRutas) {
                temp+= ruta_.outerHTML;
                if(ruta_.getAttribute("path") == path) break;
            }
            ruta.innerHTML = temp;
        }
    }

    const showContent = function (files, path, username){
        temp = "";
        files.forEach(file => {
            let newPath = `${path == '/' ? '' : path}/${file.name}`        
            temp += `<div class="col-xxl-4 col-md-4" style="width: auto;">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <span class="d-inline-block text-truncate" style="max-width: 110px;">${file.name}</span>
                        <div class="d-flex align-items-center">
                            <div class="myfiles card-icon rounded-circle d-flex align-items-center justify-content-center" path="${newPath}" user="${username}">`
                            +
                            (
                                file.isDir ? '<i class="bx bxs-folder" style="font-size: 80px; cursor: pointer;"></i>'
                                : !isImg(file.name) ? '<i class="bx bxs-file" style="font-size: 80px; cursor: pointer;" data-bs-toggle="modal" data-bs-target="#infoFile"></i>'
                                : `<img src="../storage/${username}/${newPath}" alt="${file.name}" loading="lazy" style="height: 150px;" data-bs-toggle="modal" data-bs-target="#infoFile">`
                            )
                            +
                            `</div>
                        </div>
                    </div>
                </div>
            </div>`
        });
        contenedor.innerHTML = temp;
        start();
        changeBreadCumPath(path);
    }

    const getContenidoDir = async function (path, username){
        const req = await fetch('/api/v1/folder/list', {
            method: "POST",
            body: JSON.stringify({
                dir: path
            })
        })
        const res = await req.json();

        if(res.dirname){
            const files = res.files

            showContent(files, path, username);
            saveContentPath(path, {username, files});
        }
    }

    const actionsFile = function (e) {
        const element = e.target.getAttribute("path") ? e.target : e.target.parentNode;
        const path = element.getAttribute("path");
        
        if(localStorage.getItem(path)) return showCache(path, contenedor);
        const username = element.getAttribute("user");
        getContenidoDir(path, username);
    }

    const showInfoFile = function (event){
        const element = event.relatedTarget
        const filename = element.getAttribute("alt")

        const modalTitle = modalInfoFile.querySelector('.modal-title')
        modalTitle.textContent = filename

        const modalBody = modalInfoFile.querySelector('.container-img')
        modalBody.innerHTML = filename ? `<img src="${element.src}" style="height: 250px;">` : '<h3 class="text-center">No se ha podido cargar la vista previa</h3>';
        
        const file = element.parentNode.getAttribute("path");
        fetch(`/api/v1/info-file?file=${file}`)
        .then(req => req.json())
        .then(res => {
            modalInfoFile.querySelector('.infofile-name').innerHTML = res.nombre;
            modalInfoFile.querySelector('.infofile-size').innerHTML = res.size;
            modalInfoFile.querySelector('.infofile-ctime').innerHTML = res.modificacion;

            document.getElementById("icon-download").addEventListener('click', e => downloadFile(file))
            document.getElementById("icon-delete").addEventListener('click', e => deleteFile(file))
        })
    }
    
    const contenedor = document.getElementById("contentMyFiles");
    document.getElementById("ruta").addEventListener("click", cd)

    const modalInfoFile = document.getElementById('infoFile')
    modalInfoFile.addEventListener('show.bs.modal', showInfoFile)

    start();

    const uploadFiles = document.getElementById("uploadFiles");
    const inputUploadFile = document.getElementById("inputUploadFile");
    uploadFiles.addEventListener('click', e => inputUploadFile.click());
    inputUploadFile.addEventListener("change", e => {
        const path = document.getElementById("ruta").lastElementChild.getAttribute("path");
        const data = new FormData();
        let i = 0;
        
        for(let file of inputUploadFile.files) data.append(`files_${i++}`, file);
        data.append("path", path)

        fetch("/api/v1/upload", {
            method: "POST",
            body: data
        })
        .then(req => req.json())
        .then(res => {
            if(res.status) return Swal.fire({
                icon: 'error',
                title: 'Ha ocurrido un error',
                text: res.msg
            })
            getPage(e, document.querySelector('li[page="storage"]'))
        });
    })
}
window.storage();