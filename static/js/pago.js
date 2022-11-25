window.onload = init;

function init() {
    initPayButton("paypal-button-container");
    const selectPlan = document.getElementById('selectPlan')
    selectPlan.addEventListener('change', (e)=> location.href = `${location.pathname}?tipo=${e.target.value}`)
}