// Painel Master Fábricas - Busca com debounce
(function(){
    let buscaInput = document.getElementById('painel-master-busca-input');
    let buscaForm = document.getElementById('painel-master-busca-form');
    if(buscaInput && buscaForm){
        let timer;
        buscaInput.addEventListener('input', function(){
            clearTimeout(timer);
            timer = setTimeout(function(){
                buscaForm.submit();
            }, 400);
        });
        buscaInput.addEventListener('keydown', function(e){
            if(e.key==='Enter'){
                clearTimeout(timer);
            }
        });
    }
})();
