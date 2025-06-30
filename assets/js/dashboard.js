// Painel Master Dashboard JS
// Otimizado para acessibilidade, feedback visual e robustez
window.initPainelMaster = function(){};

(function(){
    function renderNotificacoes(dados){
        let html='';
        dados.forEach(fab=>{
            // Só alerta se NÃO houver nenhum revendedor
            if(fab.revendedores===0){
                html+=`<div class='pm-error' role='alert' tabindex='0'>⚠️ ${PainelMasterI18n.atencaoSemRevendedores.replace('{nome}', `<b>${fab.nome}</b>`)}</div>`;
            }
            if(fab.erro){
                html+=`<div class='pm-error' role='alert' tabindex='0'>❌ ${PainelMasterI18n.erroFabrica.replace('{nome}', `<b>${fab.nome}</b>`).replace('{erro}', fab.erro)}</div>`;
            }
        });
        document.getElementById('painel-master-notificacoes').innerHTML=html;
    }

    function renderGraficoVendas(dados){
        const ctx=document.getElementById('grafico-vendas').getContext('2d');
        const labels=dados.map(fab=>fab.nome);
        const vendas=dados.map(fab=>(fab.mais_vendidos&&fab.mais_vendidos[0])?fab.mais_vendidos[0].valor:0);
        if(window.graficoVendasInstance)window.graficoVendasInstance.destroy();
        window.graficoVendasInstance=new Chart(ctx,{type:'bar',data:{labels:labels,datasets:[{label:PainelMasterI18n.graficoLabel,data:vendas,backgroundColor:'#4caf50'}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
    }

    function carregarDashboard(ordem='padrao', fabrica='todas'){
        // Adiciona spinner de loading
        document.getElementById('painel-master-content').innerHTML = '<div class="pm-loading" role="status" aria-live="polite">Carregando...</div>';
        fetch(PainelMasterI18n.ajaxurl+'?action=painel_master_get_dados&nonce='+PainelMasterI18n.nonce)
        .then(resp=>resp.json())
        .then(data=>{
            let temp=document.createElement('div');
            temp.innerHTML=data.data.html;
            let cards=temp.querySelectorAll('.painel-master-card');
            let dadosFabrica=Array.from(cards).map(card=>{
                let nome=card.querySelector('h3').innerText;
                let mais_vendidos=[];
                card.querySelectorAll('.pm-produtos ul').forEach((ul,idx)=>{
                    let arr=[];
                    ul.querySelectorAll('li').forEach(li=>{
                        let nome=li.querySelector('a')?.innerText||'';
                        let valor=parseInt(li.textContent.replace(/\D/g,''))||0;
                        arr.push({nome,valor});
                    });
                    if(idx===0)mais_vendidos=arr;
                });
                let revendedores=parseInt(card.querySelector('div strong+text')?.textContent?.replace(/\D/g,'')||'0');
                let ativos=parseInt(card.querySelector('.pm-ativos')?.textContent?.replace(/\D/g,'')||'0');
                let erro=card.querySelector('.pm-erro')?.textContent||'';
                return{nome,mais_vendidos,revendedores,ativos,erro,card};
            });
            // Filtro por fábrica
            if(fabrica && fabrica!=='todas'){
                dadosFabrica = dadosFabrica.filter(f=>f.nome===fabrica);
                // Remove cards não selecionados
                temp.innerHTML = '';
                dadosFabrica.forEach(f=>temp.appendChild(f.card));
            }
            document.getElementById('painel-master-content').innerHTML=temp.innerHTML;
            renderNotificacoes(dadosFabrica);
            renderGraficoVendas(dadosFabrica);
        })
        .catch(()=>{
            document.getElementById('painel-master-content').innerHTML=`<div class="pm-error" role="alert">${PainelMasterI18n.erroCarregar}</div>`;
        });
    }

    // Torna a função global ANTES de qualquer chamada
    window.initPainelMaster = initPainelMaster;
    function initPainelMaster() {
        // Adiciona canvas para gráfico
        let graficoDiv = document.createElement('div');
        graficoDiv.innerHTML = `<h2 style="margin-top:30px;">${PainelMasterI18n.graficoTitulo}</h2><canvas id="grafico-vendas" height="80" aria-label="Gráfico de vendas" role="img"></canvas>`;
        document.querySelector('.wrap').appendChild(graficoDiv);
        carregarDashboard();
        let filtro = document.getElementById('painel-master-filtro');
        if (filtro) {
            filtro.addEventListener('change', function () {
                carregarDashboard(this.value, document.getElementById('painel-master-fabrica-select')?.value||'todas');
            });
        }
        let fabricaSelect = document.getElementById('painel-master-fabrica-select');
        if(fabricaSelect){
            fabricaSelect.addEventListener('change', function(){
                carregarDashboard(document.getElementById('painel-master-filtro')?.value||'padrao', this.value);
            });
        }
    }

    document.addEventListener('DOMContentLoaded',function(){
        if(typeof Chart==='undefined'){
            // Chart.js já foi enfileirado, aguarda carregamento
            let check = setInterval(function(){
                if(typeof Chart!=='undefined'){
                    clearInterval(check);
                    initPainelMaster();
                }
            },50);
        }else{
            initPainelMaster();
        }
    });
})();


