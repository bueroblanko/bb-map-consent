/* Angaben-Assistent: Schritte, abhängige Felder, Dienstesuche, Personenliste. Ohne JavaScript stehen alle Schritte untereinander. */
(function(){
    'use strict';
    var root=document.querySelector('[data-bb-wizard]');
    if(!root){return;}
    root.classList.add('bb-js');
    var steps=[].slice.call(root.querySelectorAll('.bb-step'));
    var nav=[].slice.call(root.querySelectorAll('.bb-steps li'));
    var stepInput=root.querySelector('input[name="step"]');
    var current=Math.min(Math.max(parseInt(root.getAttribute('data-bb-step'),10)||0,0),steps.length-1);
    function show(i){
        current=i;
        steps.forEach(function(s,n){s.classList.toggle('is-active',n===i);});
        nav.forEach(function(li,n){li.classList.toggle('is-active',n===i);});
        if(stepInput){stepInput.value=String(i);}
        root.querySelectorAll('[data-bb-prev]').forEach(function(b){b.hidden=i===0;});
        root.querySelectorAll('[data-bb-next]').forEach(function(b){b.hidden=i===steps.length-1;});
    }
    nav.forEach(function(li,n){li.querySelector('a').addEventListener('click',function(e){e.preventDefault();show(n);});});
    root.querySelectorAll('[data-bb-prev]').forEach(function(b){b.addEventListener('click',function(){show(Math.max(current-1,0));window.scrollTo({top:root.offsetTop-40,behavior:'smooth'});});});
    root.querySelectorAll('[data-bb-next]').forEach(function(b){b.addEventListener('click',function(){show(Math.min(current+1,steps.length-1));window.scrollTo({top:root.offsetTop-40,behavior:'smooth'});});});

    // Felder, die nur bei einer bestimmten Auswahl gebraucht werden: data-bb-show="dpo_enabled" oder "services:matomo".
    function checked(name,value){
        var sel='input[name="fields['+name+']'+(value?'[]':'')+'"]'+(value?'[value="'+value+'"]':'');
        var el=root.querySelector(sel);return !!(el&&el.checked);
    }
    function dependencies(){
        root.querySelectorAll('[data-bb-show]').forEach(function(el){
            var rule=el.getAttribute('data-bb-show').split(':');
            el.classList.toggle('is-hidden',!checked(rule[0],rule[1]||''));
        });
        // Auftragsverarbeitung: nur ausgewählte Dienste anbieten.
        root.querySelectorAll('[data-bb-subset]').forEach(function(box){
            var source=box.getAttribute('data-bb-subset'),any=false;
            box.querySelectorAll('.bb-tile').forEach(function(tile){
                var input=tile.querySelector('input'),on=checked(source,input.value);
                tile.hidden=!on;if(!on){input.checked=false;}else{any=true;}
            });
            var empty=box.querySelector('.bb-empty');if(empty){empty.hidden=any;}
        });
        root.querySelectorAll('[data-bb-filter]').forEach(function(search){
            var box=root.querySelector('#'+search.getAttribute('data-bb-filter')),count=search.parentNode.querySelector('.bb-count');
            if(count){count.textContent=box.querySelectorAll('input:checked').length+' von '+box.querySelectorAll('input').length+' ausgewählt';}
        });
        missing();
    }
    // Offene Pflichtfelder je Schritt im Stepper zeigen.
    function missing(){
        steps.forEach(function(step,n){
            var open=0;
            step.querySelectorAll('[data-bb-required]').forEach(function(el){if(!el.closest('.is-hidden')&&!el.value.trim()){open++;}});
            var li=nav[n];if(!li){return;}
            var small=li.querySelector('small');
            if(!small.hasAttribute('data-text')){small.setAttribute('data-text',small.textContent);}
            li.classList.toggle('is-missing',open>0);
            small.textContent=open?open+(open===1?' Pflichtfeld offen':' Pflichtfelder offen'):small.getAttribute('data-text');
        });
    }
    root.addEventListener('change',dependencies);
    root.addEventListener('input',function(e){if(e.target.hasAttribute('data-bb-required')){missing();}});

    // Suche in den Diensten.
    root.querySelectorAll('[data-bb-filter]').forEach(function(search){
        var box=root.querySelector('#'+search.getAttribute('data-bb-filter'));
        search.addEventListener('input',function(){
            var q=search.value.trim().toLowerCase();
            box.querySelectorAll('.bb-tile').forEach(function(t){t.style.display=!q||t.textContent.toLowerCase().indexOf(q)>-1?'':'none';});
            box.querySelectorAll('[data-bb-group]').forEach(function(g){
                var visible=[].some.call(g.querySelectorAll('.bb-tile'),function(t){return t.style.display!=='none';});
                g.style.display=visible?'':'none';
            });
        });
    });

    // Liste (z. B. Vertretungsberechtigte): Zeilen hinzufügen und entfernen.
    root.querySelectorAll('[data-bb-list]').forEach(function(list){
        var rows=list.querySelector('.bb-rows'),tpl=list.querySelector('template'),next=rows.children.length+1;
        list.querySelector('[data-bb-add]').addEventListener('click',function(){
            var html=tpl.innerHTML.replace(/__i__/g,'n'+(next++));
            rows.insertAdjacentHTML('beforeend',html);
            var input=rows.lastElementChild.querySelector('input[type=text]');if(input){input.focus();}
        });
        rows.addEventListener('click',function(e){
            var b=e.target.closest('[data-bb-remove]');if(!b){return;}
            b.closest('.bb-row').remove();
        });
    });

    // Verantwortliche Stelle aus den Unternehmensangaben übernehmen.
    root.querySelectorAll('[data-bb-copy-address]').forEach(function(b){
        b.addEventListener('click',function(){
            var v=function(id){var el=root.querySelector('[name="fields['+id+']"]');return el?el.value.trim():'';};
            var name=v('company'),form=v('legal_form');
            if(form&&name.toLowerCase().indexOf(form.toLowerCase())===-1){name+=' '+form;}
            var lines=[name,v('street'),(v('postal_code')+' '+v('city')).trim(),v('country')].filter(Boolean);
            var target=root.querySelector('[name="fields['+b.getAttribute('data-bb-copy-address')+']"]');
            if(target.value.trim()&&!window.confirm('Vorhandenen Text ersetzen?')){return;}
            target.value=lines.join('\n');target.dispatchEvent(new Event('input',{bubbles:true}));
        });
    });

    show(current);dependencies();
})();
