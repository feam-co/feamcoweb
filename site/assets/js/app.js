
(()=>{
 const q=(s,c=document)=>c.querySelector(s),qa=(s,c=document)=>[...c.querySelectorAll(s)];
 const toggle=q('.nav-toggle'),nav=q('#main-nav');
 if(toggle&&nav){toggle.addEventListener('click',()=>{const o=nav.classList.toggle('open');toggle.setAttribute('aria-expanded',String(o));});}
 qa('.nav-group>button').forEach(b=>b.addEventListener('click',()=>b.parentElement.classList.toggle('open')));
 // Sector panel
 const sectors={}; fetch('/assets/data/sectors.json').then(r=>r.json()).then(d=>d.forEach(s=>sectors[s.id]=s)).catch(()=>{});
 const panel=q('#sector-panel');
 qa('.sector-card').forEach(card=>card.addEventListener('click',()=>{
   const s=sectors[card.dataset.sector]; if(!s||!panel)return;
   q('#sector-panel-img').src=`/assets/img/sector-${s.id}.svg`;q('#sector-panel-title').textContent=s.title;q('#sector-panel-desc').textContent=s.desc;q('#sector-panel-decisions').textContent=s.decisions;q('#sector-panel-gap').textContent=s.gap;q('#sector-panel-example').textContent=s.example;q('#sector-panel-start').textContent=s.start;panel.hidden=false;panel.scrollIntoView({behavior:'smooth',block:'center'});
 }));
 q('.panel-close')?.addEventListener('click',()=>panel.hidden=true);
 // Diagnostic
 const dlist=q('#diagnostic-list'),dresult=q('#diagnostic-result');
 function updateDiag(){if(!dlist||!dresult)return;const n=qa('button[aria-pressed="true"]',dlist).length;q('.result-orbit span',dresult).textContent=n;const label=q('.result-label',dresult),h=q('h3',dresult),p=q('p',dresult);if(n>=3){label.textContent='VERICORE’U İNCELEMENİZ FAYDALI OLABİLİR';h.textContent='Karar–icra–sonuç izleriniz parçalı olabilir.';p.textContent='Önerilen başlangıç: canlı sistemleri değiştirmeden read-only karar akışı envanteri.';}else if(n>0){label.textContent=`${n} KRİTİK İŞARET`;h.textContent='İzlemeye değer karar noktaları var.';p.textContent='Üç veya daha fazla işaret, ilk envanter görüşmesinin faydalı olabileceğini gösterir.';}else{label.textContent='HENÜZ SEÇİM YOK';h.textContent='Kurumsal karar zincirinizi birlikte açabiliriz.';p.textContent='Geçerli maddeleri işaretleyin; yüzde veya risk skoru üretmiyoruz.';}}
 dlist?.addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;b.setAttribute('aria-pressed',b.getAttribute('aria-pressed')==='true'?'false':'true');updateDiag();});
 // Decision stage
 const details={data:['01 · VERİ','Kaynak olay ve referans','Hangi sistemde, hangi kimlik ve zamanla başladı?'],rule:['02 · KURAL','Sürümlü kural sözleşmesi','Hangi ruleset, limit veya metodoloji geçerliydi?'],human:['03 · İNSAN','İnceleme, yetki ve gerekçe','Kim, hangi kapsam ve hangi delille inceledi?'],execute:['04 · İCRA','Karar ile gerçek aksiyon ayrılır','Yetki verildi; peki sistem gerçekten ne yaptı?'],outcome:['05 · SONUÇ','Gözlenen iş etkisi','İşlem tamamlandı mı, sonuç eksik mi, çelişkili mi?'],receipt:['06 · KANIT FİŞİ','Karar zinciri taşınabilir hale gelir','LINKED, UNLINKED ve CONFLICTING bağlar görünür.']};
 qa('.decision-chain button').forEach(b=>b.addEventListener('click',()=>{qa('.decision-chain button').forEach(x=>x.classList.remove('active'));b.classList.add('active');const d=details[b.dataset.stage],box=q('#stage-detail');if(box){box.innerHTML=`<span>${d[0]}</span><b>${d[1]}</b><p>${d[2]}</p>`;}}));
 // gentle parallax
 const scene=q('[data-parallax]');if(scene&&!matchMedia('(prefers-reduced-motion: reduce)').matches){scene.addEventListener('pointermove',e=>{const r=scene.getBoundingClientRect(),x=(e.clientX-r.left)/r.width-.5,y=(e.clientY-r.top)/r.height-.5;q('.core-cube',scene)?.style.setProperty('transform',`translate(-50%,-50%) rotateX(${-18-y*7}deg) rotateY(${34+x*10}deg)`);});scene.addEventListener('pointerleave',()=>q('.core-cube',scene)?.style.removeProperty('transform'));}
})();
