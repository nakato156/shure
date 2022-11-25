window.onload = init;

function ScrollAt(e) {
    const element = document.getElementById(e.target.getAttribute("tab")) 
    window.scroll({
        top: element.getBoundingClientRect().top,
        behavior: "smooth"
    })
}

function init() {
    const tabs = document.querySelectorAll(".nav-link");
    for (const tab of tabs) {
        if(tab.getAttribute("tab")) tab.addEventListener("click", ScrollAt);
    }
}