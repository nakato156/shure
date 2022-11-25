window.addEventListener('DOMContentLoaded', init)
let main, actual;

function init(e){
    const pages = document.querySelectorAll(".page");
    main = document.getElementById('main');
    
    pages.forEach(element => {
        actual = !actual ? element : actual;
        element.addEventListener('click', e => getPage(e, element));
    });
    getPage(e, actual)
}

function getPage(e, element){
    e.preventDefault();
    const page = element.getAttribute('page');
    actual.classList.remove('collapsed');
    
    const scriptPage = document.getElementById("page");
    if(scriptPage && scriptPage.getAttribute("name") == page) return;

    fetch(`/fragment/${page}`)
    .then(req => req.text())
    .then(res => {
        main.innerHTML = res
        actual = element;
        actual.classList.add('collapsed');
        addScript(page);
    });
}

function createScript(page){
    const body = document.getElementsByTagName("body")[0];

    let tagScript = document.createElement("script");
    tagScript.src = `/static/js/${page}.js`
    tagScript.setAttribute("name", page)
    body.appendChild(tagScript);
}

function addScript(page){
    const tagPage = document.querySelectorAll(`script[name='${page}']`);
    
    if(tagPage.length > 0) {
        try {
            return window[page]();
        } catch (error) {
            return createScript(page);
        }
    }

    createScript(page);
}