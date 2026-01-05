 function qs(a){return document.querySelector(a);}
 function qsa(a) {return document.querySelectorAll(a);}

 const result = qs('#result');
 const arrows = qs('#arrows');
 const arg = qs('#arg');
 const pln = qs('#pln');
 let transformed = 'no';
 let current = 'PLN';

 function change() {
    
    if (transformed == 'no') {
        arrows.style.transform = 'rotate(180deg)';
        transformed = 'yes';
        pln.classList.remove('selected');
        arg.removeAttribute('disabled');
        arg.classList.add('selected');
        pln.setAttribute('disabled', 'true');
        pln.value = '';
        arg.value = '';
        current = 'ARG';
    } else {
        arrows.style.transform = 'rotate(0deg)';
        transformed = 'no';
        arg.classList.remove('selected');
        pln.removeAttribute('disabled');
        pln.classList.add('selected');
        arg.setAttribute('disabled', 'true');
        arg.value = '';
        pln.value = '';
        current = 'PLN';
    }
    
    return current;
 }

 function calculate() {
    const selected = qs('.selected').value;
    const price = qs('#price').value;
    
    let final = ''; 

    let currency1 = '';
    let currency2 = '';
    current == 'PLN' ? currency2 = 'ARGUSÓW' : currency2 = 'ZŁ';

    if(selected == ''|| price == '') { return }

    if(current == 'PLN') {
        final = (selected/price).toFixed(2);
        arg.value = final;
    } else {
        final = (selected*price).toFixed(2);
        pln.value = final;
    }

    current == 'ARG' ? currency1 = 'ARGUSÓW' : currency1 = 'ZŁ';

    result.classList.replace('resultHidden', 'result');

    result.innerHTML = `<img src="assets/img/arg.png" alt="argus">
                        <h3>Podane przez ciebie ${selected} ${currency1} <br> to równowartość ${final} ${currency2}</h3>`
    

 }

 function hideResult() {
    result.classList.replace('result', 'resultHidden');
 }