function qs(a){return document.querySelector(a);}
function qsAll(a) {return document.querySelectorAll(a);}

function showNav(){
    const nav = qs('nav');
    const menu = qs('#menu');

    if(nav.classList.contains('hidden')) {
        nav.classList.replace('hidden','show');
        menu.classList.replace('menuButton', 'menuButtonAfter')
    }        
    else{
        nav.classList.replace('show', 'hidden');
        menu.classList.replace('menuButtonAfter', 'menuButton')
    }
        

    
}

function hideAll() {
    const divs = qsAll('#top');
    divs.forEach((a)=> {
        a.style.display = 'none';
        a.style.opacity = '0'
        
    });
}

function showTop(a) {
    hideAll()
    qs('.top' + a).style.display = 'flex';
    setTimeout(function() {
        qs('.top' + a).style.opacity = '1';
        }, 0); 
    if(a == '3') {
        qs('.top4').style.display = 'flex';
        qs('.top4').style.opacity = '1';
    }
}