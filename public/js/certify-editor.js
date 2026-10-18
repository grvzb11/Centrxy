'use strict';
(() => {
    const config = window.CertifyEditorConfig;
    if (!config) return;
    const pageW = Number(config.page_width), pageH = Number(config.page_height);
    let elements = Array.isArray(config.elements) ? config.elements : [];
    const $ = id => document.getElementById(id);
    const clamp = (v,min,max) => Math.max(min, Math.min(max,v));
    const round = v => Math.round(v*100)/100;
    const enabled = v => v === true || v === '1' || v === 1;
    const titles = {recipient_name:'Recipient name',static_text:'Fixed text',award_placement:'Award / placement',e_signature:'Signature image',signatory_name:'Signatory name',signatory_title:'Designation',signature_rule:'Signature line',signatory_details:'Signatory details',issued_date:'Current date',certificate_number:'Certificate number'};
    const descriptions = {recipient_name:'Person receiving the certificate',static_text:'Text on every certificate',award_placement:'Different award for each recipient',e_signature:'Signature image',signatory_name:'Editable signatory name',signatory_title:'Editable role or designation',signature_rule:'Independent signature line',signatory_details:'Name, designation and line together',issued_date:'Date shown on the certificate',certificate_number:'Unique certificate ID'};
    const defaultAwardGroups = () => [
        {label:'Champion',count:1}, {label:'1st Runner-Up',count:1}, {label:'2nd Runner-Up',count:1}
    ];
    const awardGroupsFor = e => {
        try {
            const source=JSON.parse(e?.settings?.award_groups||'null');
            return Array.isArray(source)&&source.length?source:defaultAwardGroups();
        } catch (_) {return defaultAwardGroups();}
    };
    const firstAward = e => {
        if(e?.settings?.award_mode==='manual') return (e.settings.award_manual||'').split(/\r?\n/)[0]||'Award';
        return awardGroupsFor(e)[0]?.label||'Champion';
    };
    const symbols = {recipient_name:'N',static_text:'T',award_placement:'★',e_signature:'✒',signatory_name:'N',signatory_title:'T',signature_rule:'―',signatory_details:'N',issued_date:'▦',certificate_number:'#'};
    // Older templates may contain repeated certificate number fields. Keep the
    // first one; saving the template will persist the cleaned field set.
    let numberSeen = false;
    const hadDuplicateNumbers = elements.filter(e => e.field_key === 'certificate_number').length > 1;
    elements = elements.filter(e => {if (e.field_key !== 'certificate_number') return true; if (numberSeen) return false; numberSeen = true; return true;});
    let selected = Math.max(0,elements.findIndex(e=>e.field_key==='recipient_name'));
    if (!elements.length) elements=[{field_key:'recipient_name',kind:'text',x_mm:25,y_mm:pageH/2-12,width_mm:pageW-50,height_mm:24,settings:{font_size:32,font_family:'dejavuserif',align:'C',color:'#192A4B',bold:false,auto_fit:true}}];
    elements.forEach(e=>{e.settings=e.settings||{};});
    // Selection is editor-only; a saved group stores just a harmless group ID
    // in settings. Fields retain independent geometry and rendering.
    let selectedFields = new Set([selected]);
    let groupSerial = 0;
    const groupIdFor = i => String(elements[i]?.settings?.editor_group || '');
    const groupMembers = i => {
        const id = groupIdFor(i);
        return id ? elements.flatMap((field, index) => groupIdFor(index) === id ? [index] : []) : [i];
    };
    selectedFields = new Set(groupMembers(selected));
    const selectionIndices = () => [...selectedFields].filter(i => i >= 0 && i < elements.length).sort((a,b)=>a-b);
    const selectSingle = i => {selected=i;selectedFields=new Set(groupMembers(i));};
    const selectionBounds = indices => {
        const minX=Math.min(...indices.map(i=>Number(elements[i].x_mm)));
        const minY=Math.min(...indices.map(i=>Number(elements[i].y_mm)));
        const maxX=Math.max(...indices.map(i=>Number(elements[i].x_mm)+Number(elements[i].width_mm)));
        const maxY=Math.max(...indices.map(i=>Number(elements[i].y_mm)+Number(elements[i].height_mm)));
        return {minX,minY,maxX,maxY};
    };
    const translate = (indices,requestedX,requestedY) => {
        if(!indices.length)return false;
        const bounds=selectionBounds(indices);
        const dx=round(clamp(requestedX,-bounds.minX,pageW-bounds.maxX));
        const dy=round(clamp(requestedY,-bounds.minY,pageH-bounds.maxY));
        if(!dx && !dy)return false;
        indices.forEach(i=>{elements[i].x_mm=round(Number(elements[i].x_mm)+dx);elements[i].y_mm=round(Number(elements[i].y_mm)+dy);});
        return true;
    };
    const syncSelectionOutline = () => {
        const canvas=$('canvas');
        canvas.querySelector('.editor-selection-outline')?.remove();
        const indices=selectionIndices();if(indices.length<2)return;
        const bounds=selectionBounds(indices);
        const outline=document.createElement('div');
        outline.className='editor-selection-outline';
        outline.style.left=(bounds.minX/pageW*100)+'%';
        outline.style.top=(bounds.minY/pageH*100)+'%';
        outline.style.width=((bounds.maxX-bounds.minX)/pageW*100)+'%';
        outline.style.height=((bounds.maxY-bounds.minY)/pageH*100)+'%';
        outline.setAttribute('aria-hidden','true');
        const label=document.createElement('span');label.textContent=`${indices.length} selected`;
        outline.append(label);
        if(!previewMode){
            const handle=document.createElement('button');
            handle.type='button';handle.className='editor-group-resize-handle';
            handle.setAttribute('aria-label','Resize selected fields together');
            handle.title='Drag to resize all selected fields proportionally';
            handle.addEventListener('pointerdown',resizeSelectedFields);
            handle.addEventListener('keydown',event=>{
                if(!['ArrowUp','ArrowDown','+','-'].includes(event.key))return;
                event.preventDefault();
                const desired=(event.key==='ArrowUp'||event.key==='+')?1.05:.95;
                flushHistory();
                if(scaleSelectedFields(indices,desired)){markDirty();saveHistory();render();}
            });
            outline.append(handle);
        }
        canvas.append(outline);
    };
    const selectionHelp = () => {
        const indices=selectionIndices(),count=indices.length;
        const grouped=count>=2 && indices.every(i=>groupIdFor(i)&&groupIdFor(i)===groupIdFor(indices[0]));
        $('editorSelectionCount').textContent=count===1?(groupIdFor(indices[0])?'1 field selected · grouped':'1 field selected'):`${count} fields selected${grouped?' · grouped':''}`;
        $('editorGroupSelection').disabled=count<2 || grouped;
        $('editorUngroupSelection').disabled=!indices.some(i=>groupIdFor(i));
        $('editorClearSelection').disabled=count<=1;
        $('editorSelectAll').disabled=count===elements.length;
        $('editorCopySelection').disabled=previewMode||!count;
        $('editorPasteSelection').disabled=previewMode||!fieldClipboard?.length;
        $('editorSelectionTip').textContent=previewMode
            ? 'Clean preview hides editing outlines. Return to Edit to move, resize, or copy fields.'
            :count>1
            ? 'Drag or resize the selection together. Ctrl+C / Ctrl+V duplicates; Backspace / Delete removes; Ctrl+Z restores.'
            : 'Ctrl / Shift + click to select fields. Backspace / Delete removes the selection; Alt bypasses snapping.';
    };
    const refreshSelection = () => {
        document.querySelectorAll('#canvas .field').forEach((box,i)=>{
            box.classList.toggle('sel',selectedFields.has(i));
            box.classList.toggle('is-primary',i===selected);
            box.classList.toggle('is-multi',selectedFields.size>1 && selectedFields.has(i));
            box.setAttribute('aria-pressed',String(selectedFields.has(i)));
        });
        document.querySelectorAll('#fieldList .editor-field-card').forEach((card,i)=>{
            card.classList.toggle('is-selected',selectedFields.has(i));
            card.classList.toggle('is-primary',i===selected);
            card.setAttribute('aria-pressed',String(selectedFields.has(i)));
        });
        selectionHelp();syncSelectionOutline();sync();
    };
    const toggleSelection = i => {
        const members=groupMembers(i);
        const all=members.every(j=>selectedFields.has(j));
        if(all && selectedFields.size>members.length)members.forEach(j=>selectedFields.delete(j));
        else members.forEach(j=>selectedFields.add(j));
        if(selectedFields.has(i))selected=i;
        else selected=selectionIndices().at(-1)??i;
        if(!selectedFields.size)selectedFields.add(selected);
    };
    let dirty=hadDuplicateNumbers;
    let zoomFactor = 1;
    let previewMode = false;
    // Bounded editor undo history. A short debounce groups a continuous drag
    // into one action, rather than recording hundreds of pointermove events.
    const serialize = () => JSON.stringify({elements,selected,selection:[...selectedFields]});
    let history = [serialize()], future = [], historyTimer = null, restoring = false;
    const refreshUndo = () => {
        const undo = $('editorUndo'), redo = $('editorRedo');
        if (undo) undo.disabled = history.length < 2;
        if (redo) redo.disabled = future.length === 0;
    };
    const saveHistory = () => {
        clearTimeout(historyTimer); historyTimer = null;
        if (restoring) return;
        const state = serialize();
        if (state !== history[history.length - 1]) {
            history.push(state);
            if (history.length > 60) history.shift();
            future = [];
            refreshUndo();
        }
    };
    const flushHistory = () => { if (historyTimer) saveHistory(); };
    const updateSaveState=()=>{
        const status=$('saveState');
        const discard=$('discardEditorChanges');
        if(status){
            status.textContent=dirty?'Unsaved changes':'All changes saved';
            status.classList.toggle('is-unsaved',dirty);
        }
        if(discard) discard.disabled=!dirty;
    };
    const markDirty=()=>{
        document.dispatchEvent(new Event('certify:editor-change')); 
        dirty=true; updateSaveState();
        clearTimeout(historyTimer);
        historyTimer=setTimeout(saveHistory,240);
    };
    // Scale the entire bounding rectangle from its top-left corner. Geometry
    // and typography scale as a single unit while each field remains editable.
    // Signature dimensions and font sizes respect the PDF-side validators.
    const scaleSelectedFields = (indices,requested,baseItems=null,baseBounds=null) => {
        if(indices.length<2)return false;
        const original=baseItems||indices.map(index=>{
            const e=elements[index];return {index,x:Number(e.x_mm),y:Number(e.y_mm),w:Number(e.width_mm),h:Number(e.height_mm),
                font_size:Number(e.settings?.font_size),title_font_size:Number(e.settings?.title_font_size)};
        });
        const bounds=baseBounds||selectionBounds(indices);
        const width=bounds.maxX-bounds.minX,height=bounds.maxY-bounds.minY;
        let low=.05,high=Math.min(8,(pageW-bounds.minX)/width,(pageH-bounds.minY)/height);
        original.forEach(item=>{
            const e=elements[item.index];
            low=Math.max(low,e.kind==='signature'?24/item.w:3/item.w,e.kind==='signature'?16/item.h:e.kind==='line'?.5/item.h:3/item.h);
            if(e.kind==='text')for(const k of ['font_size','title_font_size']){
                const size=item[k];if(Number.isFinite(size)&&size>0){low=Math.max(low,6/size);high=Math.min(high,110/size);}
            }
        });
        const factor=clamp(requested,low,Math.max(low,high));
        let changed=false;
        original.forEach(item=>{
            const e=elements[item.index];
            const x=round(bounds.minX+(item.x-bounds.minX)*factor);
            const y=round(bounds.minY+(item.y-bounds.minY)*factor);
            const w=round(item.w*factor),h=round(item.h*factor);
            changed=changed||Math.abs(x-e.x_mm)>.001||Math.abs(y-e.y_mm)>.001||Math.abs(w-e.width_mm)>.001||Math.abs(h-e.height_mm)>.001;
            Object.assign(e,{x_mm:x,y_mm:y,width_mm:w,height_mm:h});
            if(e.kind==='text')for(const key of ['font_size','title_font_size']){
                if(Number.isFinite(item[key])&&item[key]>0)e.settings[key]=round(clamp(item[key]*factor,6,110));
            }
        });
        return changed;
    };
    function resizeSelectedFields(event){
        if(previewMode||event.button!==0||selectionIndices().length<2)return;
        event.preventDefault();event.stopPropagation();flushHistory();
        const indices=selectionIndices(),bounds=selectionBounds(indices),canvas=$('canvas');
        const area=canvas.getBoundingClientRect();
        const original=indices.map(index=>{
            const e=elements[index];return {index,x:Number(e.x_mm),y:Number(e.y_mm),w:Number(e.width_mm),h:Number(e.height_mm),
                font_size:Number(e.settings?.font_size),title_font_size:Number(e.settings?.title_font_size)};
        });
        const handle=event.currentTarget,outline=handle.parentElement;
        const startX=event.clientX,startY=event.clientY;
        let changed=false,stopped=false;
        try{handle.setPointerCapture(event.pointerId);}catch(_){return;}
        const move=ev=>{
            const fractionalX=(ev.clientX-startX)*pageW/area.width/(bounds.maxX-bounds.minX);
            const fractionalY=(ev.clientY-startY)*pageH/area.height/(bounds.maxY-bounds.minY);
            const delta=Math.abs(fractionalX)>=Math.abs(fractionalY)?fractionalX:fractionalY;
            const didChange=scaleSelectedFields(indices,1+delta,original,bounds);
            changed=changed||didChange;
            original.forEach(item=>{
                const e=elements[item.index],box=canvas.querySelectorAll('.field')[item.index];if(!box)return;
                Object.assign(box.style,{left:e.x_mm/pageW*100+'%',top:e.y_mm/pageH*100+'%',
                    width:e.width_mm/pageW*100+'%',height:e.height_mm/pageH*100+'%'});
                const value=box.querySelector('.field-value');
                if(e.kind==='text'&&value){
                    if(e.field_key==='signatory_details')detailsMarkup(value,e,canvas);
                    else renderText(value,e,canvas);
                }
            });
            const now=selectionBounds(indices);
            outline.style.width=(now.maxX-now.minX)/pageW*100+'%';
            outline.style.height=(now.maxY-now.minY)/pageH*100+'%';
            const e=elements[selected];
            $('x').value=e.x_mm;$('y').value=e.y_mm;$('w').value=e.width_mm;$('h').value=e.height_mm;
        };
        const stop=()=>{
            if(stopped)return;stopped=true;
            handle.removeEventListener('pointermove',move);
            for(const ev of ['pointerup','pointercancel','lostpointercapture'])handle.removeEventListener(ev,stop);
            if(changed){markDirty();saveHistory();}
            render();
        };
        handle.addEventListener('pointermove',move);
        for(const ev of ['pointerup','pointercancel','lostpointercapture'])handle.addEventListener(ev,stop);
    }

    // Editor-local clipboard. Unlike the OS clipboard, signature asset ids
    // remain private, and Ctrl+C/Ctrl+V cannot leak image references to apps.
    let fieldClipboard=null,pasteSerial=0;
    const copySelectedFields=()=>{
        if(previewMode)return;
        const indices=selectionIndices();if(!indices.length)return;
        fieldClipboard=indices.map(i=>JSON.parse(JSON.stringify(elements[i])));
        pasteSerial=0;
        $('editorPasteSelection').disabled=false;
    };
    const pasteSelectedFields=()=>{
        if(previewMode||!fieldClipboard?.length)return;
        if(elements.length+fieldClipboard.length>20){
            window.alert(`Only ${20-elements.length} more field(s) can fit. A template supports up to 20 fields.`);return;
        }
        for(const key of ['certificate_number','award_placement']){
            if(fieldClipboard.some(e=>e.field_key===key)&&elements.some(e=>e.field_key===key)){
                window.alert(`${titles[key]} is a unique field and cannot be duplicated.`);return;
            }
        }
        flushHistory();
        ++pasteSerial;
        const clones=fieldClipboard.map(e=>JSON.parse(JSON.stringify(e)));
        const groups=new Map();
        for(const clone of clones){
            const old=String(clone.settings?.editor_group||'');
            if(old){if(!groups.has(old))groups.set(old,`grp_${Date.now().toString(36)}_${(++groupSerial).toString(36)}`);
                clone.settings.editor_group=groups.get(old);}
        }
        const firstX=Math.min(...clones.map(e=>Number(e.x_mm))), firstY=Math.min(...clones.map(e=>Number(e.y_mm)));
        const lastX=Math.max(...clones.map(e=>Number(e.x_mm)+Number(e.width_mm)));
        const lastY=Math.max(...clones.map(e=>Number(e.y_mm)+Number(e.height_mm)));
        const offset=5*pasteSerial;
        const shiftX=lastX+offset<=pageW?offset:firstX>=offset?-offset:pageW-lastX;
        const shiftY=lastY+offset<=pageH?offset:firstY>=offset?-offset:pageH-lastY;
        for(const clone of clones){clone.x_mm=round(clamp(Number(clone.x_mm)+shiftX,0,pageW-Number(clone.width_mm)));
            clone.y_mm=round(clamp(Number(clone.y_mm)+shiftY,0,pageH-Number(clone.height_mm)));}
        const start=elements.length;elements.push(...clones);
        selectedFields=new Set(clones.map((_,i)=>start+i));selected=start;
        markDirty();saveHistory();render();
    };
    const restoreState = value => {
        restoring = true;
        const state = JSON.parse(value);
        elements = state.elements;
        selected = Math.max(0, Math.min(state.selected, elements.length - 1));
        selectedFields=new Set((state.selection||[selected]).filter(i=>i>=0 && i<elements.length));
        if(!selectedFields.size)selectedFields.add(selected);
        dirty = true;
        updateSaveState();
        render(); restoring = false;
        document.dispatchEvent(new Event('certify:editor-change')); 
        refreshUndo();
    };
    const undo = () => {
        flushHistory();
        if (history.length < 2) return;
        future.push(history.pop()); restoreState(history[history.length - 1]);
    };
    const redo = () => {
        flushHistory();
        if (!future.length) return;
        const next = future.pop(); history.push(next); restoreState(next);
    };
    // Safe integration point for private draft recovery. The saved design is not touched.
    window.CertifyDraftEditor = Object.freeze({
        snapshot: () => serialize(),
        isDirty: () => dirty,
        restore: (serialized) => {
            const state = JSON.parse(serialized);
            if (!state || !Array.isArray(state.elements) || state.elements.length < 1 || state.elements.length > 20) {
                throw new Error('Invalid recovery state');
            }
            flushHistory();
            restoreState(serialized);
            saveHistory();
        }
    });
    const inlinePattern=/\{\{(AWARD|RECIPIENT|DATE|CERTIFICATE_NUMBER)\}\}/gi;
    const containsInlineAward=() => elements.some(e=>e.field_key==='static_text' && /\{\{AWARD\}\}/i.test(e.settings?.literal||''));
    const inlineValues=()=>({
        RECIPIENT:$('sampleName').value.trim()||'Recipient name',
        DATE:new Intl.DateTimeFormat('en-US',{year:'numeric',month:'long',day:'numeric'}).format(new Date()),
        CERTIFICATE_NUMBER:'CERT-7K4M-2Q9P-B6TX',
        AWARD:firstAward(elements.find(e=>e.field_key==='award_placement')),
    });
    const inlineValueText=(template)=>String(template||'').replace(inlinePattern,(_,key)=>String(inlineValues()[key.toUpperCase()]||''));
    // Token spans are created as DOM nodes, not innerHTML, so dynamic names
    // and award labels can never inject markup into the editor preview.
    const previewInlineMarkup=(node,e)=>{
        const literal=String(e.settings?.literal||'');
        node.replaceChildren();
        const values=inlineValues();let cursor=0;
        inlinePattern.lastIndex=0;
        for(const match of literal.matchAll(inlinePattern)){
            if(match.index>cursor)node.append(document.createTextNode(literal.slice(cursor,match.index)));
            const span=document.createElement('span');span.className='inline-field-value';
            span.textContent=values[match[1].toUpperCase()]||'';
            if(enabled(e.settings?.inline_emphasis)){
                span.style.fontWeight='bold';
                span.style.color=e.settings?.inline_color||e.settings?.color||'#192A4B';
            }else{span.style.fontWeight='inherit';span.style.color='inherit';}
            node.append(span);cursor=match.index+match[0].length;
        }
        if(cursor<literal.length)node.append(document.createTextNode(literal.slice(cursor)));
    };
    const testText=e=>{
        if(e.field_key==='recipient_name') return $('sampleName').value.trim()||'Recipient name';
        if(e.field_key==='award_placement') return firstAward(e);
        if(e.field_key==='certificate_number') return 'CERT-7K4M-2Q9P-B6TX';
        if(e.field_key==='issued_date') return new Intl.DateTimeFormat('en-US',{year:'numeric',month:'long',day:'numeric'}).format(new Date());
        if(e.field_key==='static_text')return inlineValueText(e.settings.literal??'Additional text');
        return e.settings.literal ?? 'Additional text';
    };
    const signatureUrl = id => /^[a-f0-9]{32}$/.test(String(id||''))
        ? (window.CertifySignatureConfig?.assetUrlPrefix||'/signatures/')+id : '';
    const signatureMarkup = (container,e) => {
        container.replaceChildren();
        const id=e.settings?.signature_id,src=signatureUrl(id);
        const imageSlot=document.createElement('div');imageSlot.className='signature-visual';
        if(src){const img=document.createElement('img');img.src=src;img.alt='Signature';img.draggable=false;imageSlot.append(img);}
        else {imageSlot.textContent='Upload or draw signature';imageSlot.classList.add('is-empty');}
        container.append(imageSlot);
        if(enabled(e.settings?.image_only))return;
        if(e.settings?.signature_line!==false && e.settings?.signature_line!=='0'){
            const line=document.createElement('div');line.className='signature-rule';container.append(line);
        }
        for(const [key,klass] of [['signatory_name','signature-person'],['signatory_title','signature-designation']]){
            const value=String(e.settings?.[key]||'').trim();
            if(!value)continue;
            const label=document.createElement('div');label.className=klass;label.textContent=value;
            container.append(label);
        }
    };
    // A signatory details field is ONE movable box; the signature image remains separate.
    const signatoryDetailsSettings=(name='Signatory name',title='Designation',line=true)=>({
        signatory_name:name,signatory_title:title,signature_line:line,
        font_family:'dejavusans',title_font_family:'dejavusans',
        font_size:11,title_font_size:9,color:'#213854',title_color:'#213854',
        bold:true,italic:false,title_bold:false,title_italic:false,align:'C',auto_fit:true
    });
    const makeSignatoryDetails=(image,settings=null)=>{
        const width=Number(image.width_mm), height=21;
        const x=round(clamp(Number(image.x_mm),0,pageW-width));
        const y=round(clamp(Number(image.y_mm)+Number(image.height_mm)+1,0,pageH-height));
        return {field_key:'signatory_details',kind:'text',x_mm:x,y_mm:y,width_mm:round(width),height_mm:height,settings:settings||signatoryDetailsSettings()};
    };
    const detailsMarkup=(node,e,canvas)=>{
        node.replaceChildren();
        const st=e.settings||{}, fontMap=window.CertifyFontCss||{};
        const line=enabled(st.signature_line);
        node.className='field-value signatory-details-value';
        node.style.setProperty('--signatory-align',st.align==='L'?'left':st.align==='R'?'right':'center');
        if(line){const hr=document.createElement('div');hr.className='signatory-details-line';node.append(hr);}
        const unit=25.4/72*(canvas.clientWidth/pageW);
        for(const [key,labelKey,sizeKey,familyKey,colorKey,boldKey,italicKey] of [
            ['name','signatory_name','font_size','font_family','color','bold','italic'],
            ['title','signatory_title','title_font_size','title_font_family','title_color','title_bold','title_italic']]){
            const item=document.createElement('div');item.className='signatory-details-'+key;
            item.textContent=String(st[labelKey]||'');
            item.style.fontFamily=fontMap[st[familyKey]]||'Arial,sans-serif';
            item.style.fontSize=Math.max(6,Number(st[sizeKey])||9)*unit+'px';
            item.style.color=st[colorKey]||'#213854';
            item.style.fontWeight=enabled(st[boldKey])?'bold':'normal';
            item.style.fontStyle=enabled(st[italicKey])?'italic':'normal';
            item.style.fontSynthesis=String(st[familyKey]||'').startsWith('installed_')?'none':'weight style';
            node.append(item);
        }
    };
    const renderText=(text,e,canvas)=>{
        const st=e.settings||{};
        const fontMap=window.CertifyFontCss||{};
        const family=fontMap[st.font_family]||'Arial, sans-serif';
        const weight=enabled(st.bold)?'bold':'normal';
        const slant=enabled(st.italic)?'italic':'normal';
        let pixels=clamp(Number(st.font_size)||32,6,110)*25.4/72*canvas.clientWidth/pageW;
        const maxW=Math.max(4,e.width_mm/pageW*canvas.clientWidth-8);
        const maxH=Math.max(4,e.height_mm/pageH*canvas.clientHeight-4);
        const context=document.createElement('canvas').getContext('2d');
        const isParagraph=e.field_key==='static_text';
        const sourceText=String(text.textContent||'').replace(/\r\n?/g,'\n');
        const lineCountAtSize=size=>{
            context.font=`${slant} ${weight} ${size}px ${family}`;
            let count=0;
            for(const paragraph of sourceText.split('\n')){
                if(!paragraph){count++;continue;}
                let line='';
                for(const word of paragraph.split(/(\s+)/).filter(Boolean)){
                    const attempt=line+word;
                    if(context.measureText(attempt).width<=maxW){line=attempt;continue;}
                    if(line.trim()){count++;line=word.trimStart();}
                    else line=word;
                    // Very long uninterrupted words can occupy more than one line.
                    if(context.measureText(line).width>maxW){
                        let chunk='';
                        for(const char of line){
                            if(chunk && context.measureText(chunk+char).width>maxW){count++;chunk='';}
                            chunk+=char;
                        }
                        line=chunk;
                    }
                }
                count++;
            }
            return count;
        };
        if(st.auto_fit===undefined||enabled(st.auto_fit)){
            if(isParagraph){
                while(pixels>6.25 && lineCountAtSize(pixels)*pixels*1.25>maxH) pixels-=.5;
            } else {
                pixels=Math.min(pixels,maxH/1.3);
                context.font=`${slant} ${weight} ${pixels}px ${family}`;
                while(pixels>6&&context.measureText(sourceText).width>maxW){pixels-=.5;context.font=`${slant} ${weight} ${pixels}px ${family}`;}
            }
        }
        text.style.whiteSpace=isParagraph?'pre-wrap':'nowrap';
        text.style.overflowWrap=isParagraph?'anywhere':'normal';
        text.style.lineHeight=isParagraph?'1.25':'normal';
        text.style.fontFamily=family;
        text.style.fontSynthesis=String(st.font_family||'').startsWith('installed_')?'none':'weight style';
        text.style.fontWeight=weight;
        text.style.fontStyle=slant;
        text.style.fontSize=Math.max(6,pixels)+'px';
        text.style.textAlign=st.align==='L'?'left':st.align==='R'?'right':'center';
        text.style.color=st.color||'#192A4B';
    };
    const signaturePanel=$('editorSignatureSettings');
    const signatureSelection=()=>elements[selected]?.kind==='signature'?elements[selected]:null;
    let signatureMethod='upload';
    let signatureBusy=false;
    const signatureStatus=(msg,error=false)=>{
        $('editorSignatureStatus').textContent=msg;
        $('editorSignatureStatus').classList.toggle('is-error',error);
    };
    const syncSignature=()=>{
        const e=signatureSelection();signaturePanel.hidden=!e;
        if(!e)return;
        const src=signatureUrl(e.settings?.signature_id);
        $('editorSignatureExisting').hidden=!src;
        if(src) $('editorSignatureThumbnail').src=src;
        const legacy=!enabled(e.settings.image_only);
        $('editorSignatureLegacyFields').hidden=!legacy;
        $('editorSignatureSplitAction').hidden=!legacy;
        $('editorSignatoryCombine').hidden=legacy||!adjacentOldSignatoryParts(selected).length;
        $('editorSignatureImageHint').hidden=legacy;
        $('editorSignatoryName').value=e.settings.signatory_name||'';
        $('editorSignatoryTitle').value=e.settings.signatory_title||'';
        $('editorSignatureLine').checked=e.settings.signature_line===undefined||enabled(e.settings.signature_line);
    };
    const adjacentOldSignatoryParts = imageIndex => {
        const indices=[];
        for(let i=imageIndex+1;i<elements.length;i++){
            if(!['signatory_name','signatory_title','signature_rule'].includes(elements[i].field_key))break;
            indices.push(i);
        }
        return indices;
    };
    const combineDetails=(imageIndex, legacy=false)=>{
        const image=elements[imageIndex];if(!image||image.kind!=='signature')return false;
        const old=adjacentOldSignatoryParts(imageIndex);
        if(!legacy && !old.length)return false;
        const lookup=key=>elements[old.find(i=>elements[i].field_key===key)]||null;
        const previousName=lookup('signatory_name');
        const previousTitle=lookup('signatory_title');
        const previousLine=lookup('signature_rule');
        const name=legacy?String(image.settings.signatory_name||'Signatory name'):String(previousName?.settings.literal||'Signatory name');
        const title=legacy?String(image.settings.signatory_title||'Designation'):String(previousTitle?.settings.literal||'Designation');
        const line=legacy?(image.settings.signature_line===undefined||enabled(image.settings.signature_line)):!!previousLine;
        const st=signatoryDetailsSettings(name,title,line);
        if(previousName){for(const k of ['font_family','font_size','color','bold','italic','align'])if(previousName.settings[k]!==undefined)st[k]=previousName.settings[k];}
        if(previousTitle){for(const [source,target] of [['font_family','title_font_family'],['font_size','title_font_size'],['color','title_color'],['bold','title_bold'],['italic','title_italic']])if(previousTitle.settings[source]!==undefined)st[target]=previousTitle.settings[source];}
        if(previousLine?.settings?.color)st.line_color=previousLine.settings.color;
        const details=makeSignatoryDetails(image,st);
        if(previousName||previousTitle||previousLine){
            const xs=[previousName,previousTitle,previousLine].filter(Boolean);
            details.x_mm=round(clamp(Math.min(...xs.map(x=>Number(x.x_mm))),0,pageW-details.width_mm));
            details.y_mm=round(clamp(Math.min(...xs.map(x=>Number(x.y_mm))),0,pageH-details.height_mm));
        }
        old.sort((a,b)=>b-a).forEach(i=>elements.splice(i,1));
        image.settings.image_only=true;
        delete image.settings.signatory_name;delete image.settings.signatory_title;delete image.settings.signature_line;
        elements.splice(imageIndex+1,0,details);
        selected=imageIndex+1;selectedFields=new Set([selected]);
        markDirty();saveHistory();render();return true;
    };
    $('editorSignatureSplitButton').addEventListener('click',()=>{
        const e=signatureSelection();if(!e)return;
        combineDetails(selected,!enabled(e.settings.image_only));
    });
    $('editorSignatoryCombineButton').addEventListener('click',()=>combineDetails(selected,false));
    const setSignatureMethod=method=>{
        signatureMethod=method;
        $('editorSignatureUploadPanel').hidden=method!=='upload';
        $('editorSignatureDrawPanel').hidden=method!=='draw';
        $('editorSignatureUploadTab').setAttribute('aria-pressed',String(method==='upload'));
        $('editorSignatureDrawTab').setAttribute('aria-pressed',String(method==='draw'));
    };
    const uploadSignature=async blob=>{
        const e=signatureSelection();if(!e||signatureBusy)return;
        if(!$('editorSignatureAuthorized').checked){signatureStatus('Confirm you are authorized to use this signature.',true);return;}
        if(!blob){signatureStatus('Choose or draw a signature first.',true);return;}
        if(blob.size>2*1024*1024){signatureStatus('The signature must be 2 MB or smaller.',true);return;}
        signatureBusy=true;
        for(const id of ['editorSignatureUploadButton','editorSignatureUsePad'])$(id).disabled=true;
        signatureStatus('Saving signature securely…');
        try{
            const data=new FormData();data.append('signature',blob,blob.name||'signature.png');data.append('authorized','1');
            const response=await fetch(window.CertifySignatureConfig.uploadUrl,{method:'POST',headers:{'X-CSRF-TOKEN':window.CertifySignatureConfig.csrf,'Accept':'application/json'},body:data,credentials:'same-origin'});
            const result=await response.json();
            if(!response.ok||!result.id)throw new Error(result.message||Object.values(result.errors||{})[0]?.[0]||'Upload failed.');
            e.settings.signature_id=result.id;
            signatureStatus('Signature saved privately. Save the template to apply it.');
            markDirty();render();
        }catch(error){signatureStatus(error.message||'Unable to upload signature.',true);}
        finally{signatureBusy=false;for(const id of ['editorSignatureUploadButton','editorSignatureUsePad'])$(id).disabled=false;}
    };
    $('editorSignatureUploadTab').addEventListener('click',()=>setSignatureMethod('upload'));
    $('editorSignatureDrawTab').addEventListener('click',()=>setSignatureMethod('draw'));
    $('editorSignatureUploadButton').addEventListener('click',()=>uploadSignature($('editorSignatureFile').files[0]));
    $('editorSignatureRemove').addEventListener('click',()=>{
        const e=signatureSelection();if(!e)return;
        delete e.settings.signature_id;markDirty();render();signatureStatus('Signature removed from this field. Save the template to apply.');
    });
    for(const [control,key] of [['editorSignatoryName','signatory_name'],['editorSignatoryTitle','signatory_title']]){
        $(control).addEventListener('input',()=>{
            const e=signatureSelection();if(!e)return;
            e.settings[key]=$(control).value;markDirty();
            const box=$('canvas').querySelectorAll('.field')[selected]?.querySelector('.field-value');
            if(box)signatureMarkup(box,e);
        });
    }
    $('editorSignatureRuleColor').addEventListener('input',()=>{
        const e=elements[selected];if(!e||e.kind!=='line')return;
        e.settings.color=$('editorSignatureRuleColor').value;markDirty();
        $('canvas').querySelectorAll('.field')[selected]?.querySelector('.signature-rule-field')?.style.setProperty('--signature-rule-color',e.settings.color);
    });
    $('editorSignatureLine').addEventListener('change',()=>{
        const e=signatureSelection();if(!e)return;
        e.settings.signature_line=$('editorSignatureLine').checked;markDirty();render();
    });
    const pad=$('editorSignaturePad'),ink=pad.getContext('2d');
    let drawing=false,hasInk=false,lastPoint=null;
    const padPoint=ev=>{const rect=pad.getBoundingClientRect();return {x:(ev.clientX-rect.left)*pad.width/rect.width,y:(ev.clientY-rect.top)*pad.height/rect.height};};
    pad.addEventListener('pointerdown',ev=>{
        if(ev.button!==0)return;ev.preventDefault();drawing=true;
        pad.setPointerCapture(ev.pointerId);lastPoint=padPoint(ev);
        ink.beginPath();ink.arc(lastPoint.x,lastPoint.y,2.4,0,Math.PI*2);ink.fillStyle='#213854';ink.fill();hasInk=true;
    });
    pad.addEventListener('pointermove',ev=>{
        if(!drawing)return;ev.preventDefault();const pos=padPoint(ev);
        ink.strokeStyle='#213854';ink.lineWidth=4.5;ink.lineCap='round';ink.lineJoin='round';
        ink.beginPath();ink.moveTo(lastPoint.x,lastPoint.y);ink.lineTo(pos.x,pos.y);ink.stroke();lastPoint=pos;
    });
    const stopInk=()=>{drawing=false;lastPoint=null;};
    pad.addEventListener('pointerup',stopInk);pad.addEventListener('pointercancel',stopInk);
    $('editorSignatureClearPad').addEventListener('click',()=>{ink.clearRect(0,0,pad.width,pad.height);hasInk=false;signatureStatus('Drawing cleared.');});
    $('editorSignatureUsePad').addEventListener('click',()=>{
        if(!hasInk){signatureStatus('Draw a signature on the pad first.',true);return;}
        pad.toBlob(blob=>uploadSignature(blob),'image/png');
    });
    const awardSettings=$('editorAwardSettings');
    const awardRows=$('editorAwardGroupRows');
    const awardManual=$('editorAwardManual');
    const awardModeButtons=[...document.querySelectorAll('input[name="editor_award_mode"]')];
    const awardSelection=()=>elements[selected]?.field_key==='award_placement'?elements[selected]:null;
    const formatOrdinal=n=>{
        const suffix=(n%100>=11&&n%100<=13)?'th':n%10===1?'st':n%10===2?'nd':n%10===3?'rd':'th';
        return `${n}${suffix}`;
    };
    const awardLines = e => String(e.settings.award_manual||'').split(/\r\n|\n|\r/);
    const awardState = e => {
        const manual=e.settings.award_mode==='manual';
        if (manual) {
            const raw=String(e.settings.award_manual||'');
            const lines=raw?awardLines(e):[];
            const invalid=lines.some(line=>!line.trim()||line.trim().length>100);
            return {manual, count:lines.length,invalid,lines,groups:[]};
        }
        const groups=awardGroupsFor(e);
        const invalid=groups.some(group=>!String(group.label||'').trim()||String(group.label).trim().length>100||
            !Number.isInteger(Number(group.count))||Number(group.count)<1||Number(group.count)>1000);
        const count=groups.reduce((sum,group)=>sum+(Number(group.count)||0),0);
        return {manual,count,invalid,lines:[],groups};
    };
    const updateAwardSummary=()=>{
        const e=awardSelection();if(!e)return;
        const state=awardState(e),count=state.count;
        const invalid=state.invalid||count<1||count>1000;
        const displayCount=Number.isFinite(count)?count:0;
        $('editorAwardCount').textContent=String(displayCount);
        $('editorAwardSummary').textContent=count===0?'No awards assigned':`${count} ${count===1?'certificate':'certificates'}`;
        $('editorAwardHint').textContent=state.invalid
            ?(state.manual?'Every line needs an award title (up to 100 characters).':'Each award needs a title and 1–1,000 people.')
            :count>1000?'Maximum 1,000 recipients per batch.':count===0?'Add an award to continue.':`Enter ${count} ${count===1?'name':'names'} on Generate.`;
        $('editorAwardSummary').classList.toggle('is-error',invalid);
        $('editorAwardCount').classList.toggle('is-error',invalid);
        $('editorAwardLineCount').textContent=`${count} ${count===1?'entry':'entries'}`;
        $('editorAwardModeHint').textContent=state.manual
            ?'Type one award per line, in the order of recipient names.':
            'Give the same award to several people by setting its count.';
        const list=$('editorAwardPreviewList');list.replaceChildren();
        let shown=0;
        const addRow=title=>{
            if(shown>=6)return;
            const item=document.createElement('li');
            const index=document.createElement('span');index.className='editor-award-preview-index';index.textContent=`#${shown+1}`;
            const label=document.createElement('span');label.className='editor-award-preview-title';label.textContent=title;
            item.append(index,label);list.append(item);shown++;
        };
        if(state.manual) {
            state.lines.slice(0,6).forEach(title=>addRow(title.trim()||'—'));
        } else {
            for(const group of state.groups) {
                const n=Math.min(6-shown,Math.max(0,Number(group.count)||0));
                for(let i=0;i<n;i++)addRow(String(group.label||'').trim()||'—');
                if(shown>=6)break;
            }
        }
        const remaining=Math.max(0,count-shown);
        $('editorAwardPreviewMore').hidden=!remaining;
        $('editorAwardPreviewMore').textContent=remaining?`+ ${remaining} more ${remaining===1?'certificate':'certificates'}`:'';
        $('editorAwardPreviewCount').textContent=count?`First ${shown} of ${count}`:'';
    };
    const refreshAwardPreview=()=>{
        const e=awardSelection();if(!e)return;
        const field=$('canvas').querySelectorAll('.field')[selected]?.querySelector('.field-value');
        if(field){if(e.field_key==='static_text')previewInlineMarkup(field,e);else field.textContent=testText(e);renderText(field,e,$('canvas'));}
    };
    const changeAward=()=>{markDirty();updateAwardSummary();refreshAwardPreview();};
    const renderAwardRows=()=>{
        const e=awardSelection();if(!e)return;
        const groups=awardGroupsFor(e);awardRows.replaceChildren();
        groups.forEach((group,index)=>{
            const row=document.createElement('div');row.className='editor-award-group-row';
            const number=document.createElement('span');number.className='editor-award-row-index';number.textContent=String(index+1);number.setAttribute('aria-hidden','true');
            const label=document.createElement('input');label.type='text';label.maxLength=100;label.value=group.label;
            label.setAttribute('aria-label',`Award title ${index+1}`);label.placeholder='Award title';
            label.addEventListener('input',()=>{group.label=label.value;e.settings.award_groups=JSON.stringify(groups);changeAward();});
            const count=document.createElement('input');count.type='number';count.min='1';count.max='1000';count.step='1';count.value=group.count;
            count.setAttribute('aria-label',`Number of people receiving award ${index+1}`);
            count.addEventListener('input',()=>{group.count=count.value;e.settings.award_groups=JSON.stringify(groups);changeAward();});
            const remove=document.createElement('button');remove.type='button';remove.className='editor-award-delete';
            remove.setAttribute('aria-label',`Remove placement ${index+1}`);remove.title='Remove placement';remove.textContent='×';remove.disabled=groups.length===1;
            remove.addEventListener('click',()=>{groups.splice(index,1);e.settings.award_groups=JSON.stringify(groups);renderAwardRows();changeAward();});
            row.append(number,label,count,remove);awardRows.append(row);
        });
        $('editorAddAwardGroup').disabled=groups.length>=100;
        updateAwardSummary();
    };
    const syncAward=()=>{
        const e=awardSelection();awardSettings.hidden=!e;if(!e)return;
        e.settings.award_mode=e.settings.award_mode==='manual'?'manual':'grouped';
        if(!e.settings.award_groups)e.settings.award_groups=JSON.stringify(defaultAwardGroups());
        if(e.settings.award_manual===undefined)e.settings.award_manual='';
        awardModeButtons.forEach(button=>button.checked=button.value===e.settings.award_mode);
        $('editorAwardGroupsPanel').hidden=e.settings.award_mode!=='grouped';
        $('editorAwardManualPanel').hidden=e.settings.award_mode!=='manual';
        awardManual.value=e.settings.award_manual;
        $('editorAwardInlineOnly').checked=enabled(e.settings.inline_only);
        renderAwardRows();
    };
    $('editorAwardInlineOnly').addEventListener('change',()=>{
        const e=awardSelection();if(!e)return;
        e.settings.inline_only=$('editorAwardInlineOnly').checked;
        markDirty();render();
    });
    awardModeButtons.forEach(button=>button.addEventListener('change',()=>{
        const e=awardSelection();if(!e||!button.checked)return;
        e.settings.award_mode=button.value;syncAward();changeAward();
    }));
    awardManual.addEventListener('input',()=>{
        const e=awardSelection();if(!e)return;
        e.settings.award_manual=awardManual.value;changeAward();
    });
    $('editorAddAwardGroup').addEventListener('click',()=>{
        const e=awardSelection();if(!e)return;
        const groups=awardGroupsFor(e);if(groups.length>=100)return;
        const rank=groups.length;
        groups.push({label:`${formatOrdinal(rank)} Runner-Up`,count:1});
        e.settings.award_groups=JSON.stringify(groups);
        renderAwardRows();changeAward();
        awardRows.lastElementChild?.querySelector('input')?.focus();
    });
    const detailsPanel=$('editorSignatoryDetailsSettings');
    const detailSelection=()=>elements[selected]?.field_key==='signatory_details'?elements[selected]:null;
    const detailsAppearance=$('editorSignatoryAppearance');
    const detailBooleanKeys=['bold','italic','title_bold','title_italic'];
    const detailStyleButtons={
        editorDetailsNameBold:'bold',editorDetailsNameItalic:'italic',
        editorDetailsTitleBold:'title_bold',editorDetailsTitleItalic:'title_italic'
    };
    // Keep legacy font/color keys for old templates and the PDF renderer. A shared
    // choice updates BOTH keys; loading a legacy template with mismatched values
    // never changes them until the user deliberately selects a common value.
    const detailBindings={
        editorDetailsName:'signatory_name',editorDetailsTitle:'signatory_title',
        editorDetailsNameSize:'font_size',editorDetailsTitleSize:'title_font_size',
        editorDetailsLine:'signature_line'
    };
    const detailsStyleSupported=(st,prefix,style)=>{
        const family=String(st[prefix==='name'?'font_family':'title_font_family']||'dejavusans');
        if(!family.startsWith('installed_') || !style)return true;
        return !!(window.CertifyFontVariants||{})[family]?.[style];
    };
    const detailStyle=(st,prefix,overrideKey=null,overrideValue=null)=>{
        const name=prefix==='name';
        const boldKey=name?'bold':'title_bold',italicKey=name?'italic':'title_italic';
        const bold=overrideKey===boldKey?overrideValue:enabled(st[boldKey]);
        const italic=overrideKey===italicKey?overrideValue:enabled(st[italicKey]);
        return (bold?'B':'')+(italic?'I':'');
    };
    const refreshDetailsStyles=e=>{
        for(const [id,key] of Object.entries(detailStyleButtons)){
            const button=$(id), prefix=key.startsWith('title_')?'title':'name';
            const current=enabled(e.settings[key]);
            const nextStyle=detailStyle(e.settings,prefix,key,!current);
            button.setAttribute('aria-pressed',String(current));
            button.disabled=!detailsStyleSupported(e.settings,prefix,nextStyle);
            button.title=button.disabled?'This font variant is not installed. Change the shared font or choose an available style.':key.endsWith('italic')||key==='italic'?'Italic':'Bold';
        }
    };
    // Signatory styling affects only the selected text. Rebuilding the entire
    // field list on every Bold/Italic click destroys scroll anchors and makes
    // the inspector jump to the top. Paint the active canvas value in place.
    const paintActiveSignatory=()=>{
        const e=detailSelection();if(!e)return;
        const canvas=$('canvas');
        const field=canvas.querySelectorAll('.field')[selected];
        const value=field?.querySelector('.field-value');
        if(value)detailsMarkup(value,e,canvas);
        const preview=$('fieldList').querySelectorAll('.editor-field-card-copy small')[selected];
        if(preview)preview.textContent=[e.settings.signatory_name,e.settings.signatory_title].filter(Boolean).join(' · ');
        refreshDetailsStyles(e);
    };
    for(const [id,key] of Object.entries(detailBindings)){
        $(id).addEventListener(key==='signature_line'?'change':'input',()=>{
            const e=detailSelection();if(!e)return;
            const node=$(id);
            e.settings[key]=key==='signature_line'?node.checked:
                (['font_size','title_font_size'].includes(key)?clamp(Number(node.value)||9,6,110):node.value);
            markDirty();paintActiveSignatory();
        });
    }
    for(const [id,key] of Object.entries(detailStyleButtons)){
        $(id).addEventListener('click',()=>{
            const e=detailSelection();if(!e)return;
            const next=!enabled(e.settings[key]);
            const prefix=key.startsWith('title_')?'title':'name';
            if(!detailsStyleSupported(e.settings,prefix,detailStyle(e.settings,prefix,key,next)))return;
            e.settings[key]=next;
            $('editorDetailsStyleNote').hidden=true;
            markDirty();paintActiveSignatory();
        });
    }
    $('fontFamily').querySelectorAll('option').forEach(opt=>$('editorDetailsFont').append(opt.cloneNode(true)));
    const mixedFontOption=document.createElement('option');
    mixedFontOption.value='__mixed__';mixedFontOption.textContent='Different fonts · choose to unify';
    mixedFontOption.disabled=true;
    $('editorDetailsFont').prepend(mixedFontOption);
    $('editorDetailsFont').addEventListener('change',()=>{
        const e=detailSelection();if(!e)return;
        const font=$('editorDetailsFont').value;if(font==='__mixed__')return;
        e.settings.font_family=font;e.settings.title_font_family=font;
        const cleared=[];
        for(const prefix of ['name','title']){
            if(!detailsStyleSupported(e.settings,prefix,detailStyle(e.settings,prefix))){
                const name=prefix==='name';
                e.settings[name?'bold':'title_bold']=false;
                e.settings[name?'italic':'title_italic']=false;
                cleared.push(name?'name':'designation');
            }
        }
        const note=$('editorDetailsStyleNote');
        note.hidden=!cleared.length;
        if(cleared.length)note.textContent='Unavailable Bold/Italic styles were switched off for '+cleared.join(' and ')+'. Install the matching font variants to use them.';
        markDirty();paintActiveSignatory();
    });
    $('editorDetailsColor').addEventListener('input',()=>{
        const e=detailSelection();if(!e)return;
        const color=$('editorDetailsColor').value;
        e.settings.color=color;e.settings.title_color=color;
        markDirty();paintActiveSignatory();
    });
    const syncDetails=()=>{
        const e=detailSelection();
        detailsPanel.hidden=!e;
        detailsAppearance.hidden=!e;
        $('editorStandardTextStyle').hidden=!!e;
        if(!e)return;
        for(const [id,key] of Object.entries(detailBindings)){
            const node=$(id);
            if(key==='signature_line')node.checked=enabled(e.settings[key]);
            else node.value=e.settings[key]??'';
        }
        const a=String(e.settings.font_family||'dejavusans'),b=String(e.settings.title_font_family||'dejavusans');
        $('editorDetailsFont').value=a===b?a:'__mixed__';
        const colorA=String(e.settings.color||'#213854'),colorB=String(e.settings.title_color||'#213854');
        $('editorDetailsColor').value=/^#[0-9a-f]{6}$/i.test(colorA)?colorA:'#213854';
        const notices=[];
        if(a!==b)notices.push('Different fonts saved — choose a font to apply it to both.');
        if(colorA.toLowerCase()!==colorB.toLowerCase())notices.push('Different colors saved — pick a color to apply it to both.');
        const note=$('editorDetailsSharedNote');
        note.hidden=!notices.length;
        note.textContent=notices.join(' ');
        refreshDetailsStyles(e);
    };
    const sync=()=>{
        const e=elements[selected];if(!e)return;
        $('elementTitle').textContent=titles[e.field_key]||'Text';
        $('selection').value=String(selected);
        $('literalWrap').hidden=!['static_text','signatory_name','signatory_title'].includes(e.field_key);
        $('editorInlineTools').hidden=e.field_key!=='static_text';
        $('editorInlineEmphasis').checked=enabled(e.settings.inline_emphasis);
        $('editorInlineColor').value=/^#[0-9a-f]{6}$/i.test(e.settings.inline_color||'')?e.settings.inline_color:'#192A4B';
        $('editorInlineColorRow').hidden=!enabled(e.settings.inline_emphasis);
        $('editorConfigureInlineAwards').hidden=e.field_key!=='static_text'||!(elements.some(item=>item.field_key==='award_placement')||/\{\{AWARD\}\}/i.test(e.settings.literal||''));
        $('editorLiteralLabel').textContent=e.field_key==='signatory_name'?'Signatory name':e.field_key==='signatory_title'?'Designation':'Certificate paragraph';
        $('editorSignatureRuleSettings').hidden=e.kind!=='line';
        if(e.kind==='line')$('editorSignatureRuleColor').value=/^#[0-9a-f]{6}$/i.test(e.settings.color||'')?e.settings.color:'#8695a9';
        syncAward();
        syncSignature();
        syncDetails();
        $('editorTabStyle').disabled=e.kind!=='text';
        $('editorTabStyle').title=e.kind==='signature'?'Signature image does not use a font; select its signatory name or designation field instead.':e.kind==='line'?'Use Signature line settings for line color.':'';
        if(e.kind!=='text' && $('editorTabStyle').getAttribute('aria-selected')==='true'){
            $('editorTabFields').click();
        }
        $('literal').value=e.settings.literal||'';
        $('literalCount').textContent=`${$('literal').value.length} / 2000`;
        for(const [field,key] of Object.entries({x:'x_mm',y:'y_mm',w:'width_mm',h:'height_mm'})) $(field).value=round(Number(e[key]));
        $('fontFamily').value=e.settings.font_family||'dejavuserif';
        $('fontSize').value=e.settings.font_size||32;
        $('color').value=e.settings.color||'#192A4B';
        $('colorHex').textContent=String($('color').value).toUpperCase();
        $('align').value=e.settings.align||'C';
        document.querySelectorAll('.editor-text-align-buttons button').forEach(button=>{button.setAttribute('aria-pressed',String(button.dataset.align===$('align').value));});
        $('bold').checked=enabled(e.settings.bold);
        $('italic').checked=enabled(e.settings.italic);
        const custom = String(e.settings.font_family||'').startsWith('installed_');
        const available = (window.CertifyFontVariants||{})[e.settings.font_family] || {B: false, I: false, BI: false};
        for (const id of ['bold', 'italic']) {
            const toggle = $(id);
            const targetB = id === 'bold' ? !toggle.checked : $('bold').checked;
            const targetI = id === 'italic' ? !toggle.checked : $('italic').checked;
            const targetStyle = (targetB?'B':'') + (targetI?'I':'');
            toggle.disabled = custom && !!targetStyle && !available[targetStyle];
            const button = $(id + 'Toggle');
            if (!button) continue;
            button.setAttribute('aria-pressed', String(toggle.checked));
            button.disabled = toggle.disabled;
            button.title = toggle.disabled ? 'The corresponding TTF variant is not installed; open Fonts → Manage font' : `${id === 'bold' ? 'Bold' : 'Italic'} text`;
        }
        const tip = $('customFontStyleTip');
        tip.hidden = !custom || (available.B && available.I && available.BI);
        if (!tip.hidden) tip.textContent = 'Available styles: Regular' + (available.B?', Bold':'') + (available.I?', Italic':'') + (available.BI?', Bold Italic':'') + '. Add missing TTF faces from Fonts → Manage font.';
        $('autoFit').checked=e.settings.auto_fit===undefined||enabled(e.settings.auto_fit);
    };
    // Fit by BOTH available width and height. On the desktop editor the stage
    // occupies the remaining viewport, so the certificate is never clipped.
    const fitCanvas=()=>{
        const canvas=$('canvas'),stage=$('editorStage');
        const stageStyle=getComputedStyle(stage);
        const hPad=(parseFloat(stageStyle.paddingLeft)||0)+(parseFloat(stageStyle.paddingRight)||0);
        const vPad=(parseFloat(stageStyle.paddingTop)||0)+(parseFloat(stageStyle.paddingBottom)||0);
        const room=Math.max(130,stage.clientWidth-hPad-8);
        const singleScreen=window.matchMedia('(min-width:1021px) and (min-height:670px)').matches;
        const full=document.fullscreenElement===stage;
        const availableH=(singleScreen||full)
            ? stage.clientHeight-vPad-8
            : Math.min(830,window.innerHeight-240);
        const heightLimit=Math.max(130,availableH);
        const fitted=Math.max(100,Math.min(room,heightLimit*pageW/pageH));
        canvas.style.width=round(fitted*zoomFactor)+'px';
        stage.classList.toggle('zoomed',zoomFactor>1.01);
    };
    let resizeQueued=false;
    const refit=()=>{
        if(resizeQueued)return;
        resizeQueued=true;
        requestAnimationFrame(()=>{
            resizeQueued=false;
            const canvas=$('canvas');
            if(!canvas.isConnected)return;
            fitCanvas();
            canvas.querySelectorAll('.field-value').forEach((value,i)=>{
                if(elements[i] && elements[i].kind==='text')renderText(value,elements[i],canvas);
            });
        });
    };
    const render=()=>{
        const canvas=$('canvas');fitCanvas();canvas.querySelectorAll('.field').forEach(n=>n.remove());
        const select=$('selection');select.replaceChildren();
        const fieldList=$('fieldList');fieldList.replaceChildren();
        $('fieldCount').textContent=`${elements.length} ${elements.length===1?'field':'fields'} on certificate`;
        selected=clamp(selected,0,elements.length-1);
        selectedFields=new Set(selectionIndices());
        if(!selectedFields.size)selectedFields.add(selected);
        elements.forEach((e,i)=>{
            const opt=document.createElement('option');opt.value=String(i);opt.textContent=`${i+1}. ${titles[e.field_key]||'Text'}`;select.append(opt);
            const card=document.createElement('button');card.type='button';card.className='editor-field-card'+(selectedFields.has(i)?' is-selected':'')+(i===selected?' is-primary':'');
            card.setAttribute('aria-pressed',String(selectedFields.has(i)));card.setAttribute('aria-label',`Edit ${titles[e.field_key]||'Text'} field ${i+1}`);
            const symbol=document.createElement('span');symbol.className='editor-field-card-icon';symbol.textContent=symbols[e.field_key]||'T';
            const details=document.createElement('span');details.className='editor-field-card-copy';
            const heading=document.createElement('strong');heading.textContent=titles[e.field_key]||'Text';
            card.title=`Edit ${titles[e.field_key]||'Text'}`;
            // A short, safely truncated text preview helps distinguish the many
            // Fixed Text fields without exposing a full paragraph in the list.
            details.append(heading);
            const sub=document.createElement('small');
            if(['static_text','signatory_name','signatory_title'].includes(e.field_key)) {
                const content=String(e.settings?.literal||'').replace(/\s+/g,' ').trim();
                sub.textContent=content ? content.slice(0,65)+(content.length>65?'…':'') : 'Empty fixed text';
                if(content) card.title=content.slice(0,240);
            } else if(e.field_key==='signatory_details')sub.textContent=[e.settings.signatory_name,e.settings.signatory_title].filter(Boolean).join(' · ');
            else if(e.kind==='signature') sub.textContent=e.settings?.signatory_name || (e.settings?.signature_id?'Signature ready':'Upload or draw signature');
            else if(e.field_key==='award_placement' && enabled(e.settings?.inline_only))sub.textContent='Used inside paragraph · no separate overlay';
            else sub.textContent=descriptions[e.field_key]||'Editable text';
            details.append(sub);
            card.append(symbol,details);
            card.addEventListener('click',event=>{if(event.ctrlKey||event.metaKey||event.shiftKey)toggleSelection(i);else selectSingle(i);render();});
            const row=document.createElement('div');row.className='editor-field-row';
            row.append(card);
            // Keep deletion independent of the selection button so both controls
            // remain accessible to keyboard and screen-reader users.
            if(elements.length > 1 && !(e.field_key==='recipient_name' && elements.filter(item=>item.field_key==='recipient_name').length===1)) {
                const remove=document.createElement('button');remove.type='button';remove.className='editor-row-remove';
                remove.title='Remove '+(titles[e.field_key]||'field');
                remove.setAttribute('aria-label','Remove '+(titles[e.field_key]||'field'));
                remove.textContent='×';
                remove.addEventListener('click',()=>removeField(i));
                row.append(remove);
            }
            fieldList.append(row);
            const box=document.createElement('div');box.className='field'+(selectedFields.has(i)?' sel':'')+(i===selected?' is-primary':'')+(selectedFields.size>1 && selectedFields.has(i)?' is-multi':'');box.setAttribute('role','button');box.setAttribute('aria-pressed',String(selectedFields.has(i)));box.setAttribute('aria-label',titles[e.field_key]||'Text');box.tabIndex=previewMode?-1:0;
            if(e.field_key==='award_placement' && enabled(e.settings.inline_only))box.classList.add('is-inline-source');
            const place=()=>{box.style.left=e.x_mm/pageW*100+'%';box.style.top=e.y_mm/pageH*100+'%';box.style.width=e.width_mm/pageW*100+'%';box.style.height=e.height_mm/pageH*100+'%';};place();
            box.style.justifyContent=e.settings.align==='L'?'flex-start':e.settings.align==='R'?'flex-end':'center';
            const value=document.createElement(e.kind==='signature'||e.kind==='line'?'div':'span');value.className='field-value';
            if(e.kind==='signature'){value.classList.add('signature-field-value');signatureMarkup(value,e);}
            else if(e.kind==='line'){value.classList.add('signature-rule-field');value.style.setProperty('--signature-rule-color',e.settings.color||'#8695A9');}
            else if(e.field_key==='signatory_details')detailsMarkup(value,e,canvas);
            else if(e.field_key==='static_text')previewInlineMarkup(value,e);
            else value.textContent=testText(e);
            box.append(value);
            if(i===selected && selectedFields.size===1){const tag=document.createElement('span');tag.className='field-label';tag.textContent=titles[e.field_key]||'Text';box.append(tag);const handle=document.createElement('span');handle.className='handle';handle.title='Drag to resize';box.append(handle);handle.addEventListener('pointerdown',event=>drag(event,i,true));}
            box.addEventListener('pointerdown',event=>{
                if(previewMode||event.target.classList.contains('handle')||event.button!==0)return;
                if(event.ctrlKey||event.metaKey||event.shiftKey){event.preventDefault();toggleSelection(i);render();return;}
                if(!selectedFields.has(i)){selectSingle(i);refreshSelection();}
                else if(selectedFields.size===1 && groupIdFor(i)){selectSingle(i);refreshSelection();}
                drag(event,i,false);
            });
            box.addEventListener('keydown',event=>{
                if(previewMode)return;
                if(event.key==='Escape'){event.preventDefault();selected=i;selectedFields=new Set([i]);render();return;}
                if(!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key))return;
                event.preventDefault();
                if(!selectedFields.has(i))selectSingle(i);
                const amount=event.shiftKey?5:1;
                const dx=event.key==='ArrowLeft'?-amount:event.key==='ArrowRight'?amount:0;
                const dy=event.key==='ArrowUp'?-amount:event.key==='ArrowDown'?amount:0;
                if(translate(selectionIndices(),dx,dy)){markDirty();render();$('canvas').querySelectorAll('.field')[i]?.focus({preventScroll:true});}
            });
            canvas.append(box);if(e.kind==='text' && e.field_key!=='signatory_details')renderText(value,e,canvas);
        });
        sync();selectionHelp();syncSelectionOutline();
    };
    // Smart alignment works with the bounding rectangle of the entire selection.
    // It deliberately never edits individual offsets: moving a group preserves
    // spacing, and references are always fields outside the selection.
    const closestAlignment = (axis, desired, bounds, selectedIndices, pixelScale) => {
        const horizontal = axis === 'x';
        const pageSize = horizontal ? pageW : pageH;
        const first = horizontal ? bounds.minX : bounds.minY;
        const last = horizontal ? bounds.maxX : bounds.maxY;
        const startKey = horizontal ? 'x_mm' : 'y_mm';
        const lengthKey = horizontal ? 'width_mm' : 'height_mm';
        const low = -first, high = pageSize - last;
        // Roughly seven screen pixels, with a strict millimeter cap.
        const tolerance = Math.min(2.5, Math.max(0.8, 7 * pixelScale));
        const own = [{name:'start',value:first},{name:'center',value:(first+last)/2},{name:'end',value:last}];
        const candidates = [
            {own:'center',value:pageSize/2,kind:'page-center'},
            {own:'start',value:0,kind:'page-edge'},
            {own:'end',value:pageSize,kind:'page-edge'}
        ];
        const selectedSet = new Set(selectedIndices);
        elements.forEach((e,index) => {
            if (selectedSet.has(index)) return;
            const from=Number(e[startKey]), to=from+Number(e[lengthKey]);
            if (![from,to].every(Number.isFinite)) return;
            for (const [own,value] of [['start',from],['center',(from+to)/2],['end',to]]) candidates.push({own,value,kind:'field'});
        });
        let best=null;
        for (const candidate of candidates) {
            for (const point of own) {
                if (candidate.own && candidate.own !== point.name) continue;
                const correction=candidate.value-(point.value+desired);
                const adjusted=desired+correction;
                if (Math.abs(correction)>tolerance || adjusted<low-0.001 || adjusted>high+0.001) continue;
                // Favor the page's center over incidental nearby field edges.
                const score=Math.abs(correction)-(candidate.kind==='page-center'?0.18:0);
                if (!best || score<best.score) best={score,delta:adjusted,guide:candidate.value,kind:candidate.kind};
            }
        }
        return best;
    };
    const makeAlignmentGuide = axis => {
        const line=document.createElement('div');
        line.className=`editor-alignment-guide editor-alignment-guide--${axis}`;
        line.setAttribute('aria-hidden','true');
        line.hidden=true;
        $('canvas').append(line);
        return line;
    };
    const setAlignmentGuide = (line, axis, snap) => {
        line.hidden=!snap;
        if(!snap) return;
        const coordinate=100*snap.guide/(axis==='x'?pageW:pageH);
        line.style[axis==='x'?'left':'top']=coordinate+'%';
        line.classList.toggle('is-page-center',snap.kind==='page-center');
    };
    function drag(event,i,isResize){
        if(previewMode||event.button!==0)return;
        event.preventDefault();event.stopPropagation();
        if(isResize && selectedFields.size!==1)return;
        flushHistory();
        const indices=isResize?[i]:selectionIndices();
        const area=$('canvas').getBoundingClientRect(),capture=event.currentTarget;
        const original=indices.map(index=>({index,x:Number(elements[index].x_mm),y:Number(elements[index].y_mm),w:Number(elements[index].width_mm),h:Number(elements[index].height_mm)}));
        const bounds=selectionBounds(indices);
        const startX=event.clientX,startY=event.clientY;
        const canvas=$('canvas');
        let guideX=null, guideY=null;
        let changed=false;
        try{capture.setPointerCapture(event.pointerId);}catch(_){return;}
        if(!isResize){
            guideX=makeAlignmentGuide('x');
            guideY=makeAlignmentGuide('y');
        }
        const move=ev=>{
            const dx=(ev.clientX-startX)*pageW/area.width;
            const dy=(ev.clientY-startY)*pageH/area.height;
            if(isResize){
                const base=original[0],e=elements[i];
                e.width_mm=round(clamp(base.w+dx,5,pageW-base.x));
                e.height_mm=round(clamp(base.h+dy,5,pageH-base.y));
            } else {
                // Clamp a single shared delta: relative spacing never changes,
                // and no selected field crosses the certificate boundary.
                let moveX=clamp(dx,-bounds.minX,pageW-bounds.maxX);
                let moveY=clamp(dy,-bounds.minY,pageH-bounds.maxY);
                const snapX=ev.altKey?null:closestAlignment('x',moveX,bounds,indices,pageW/area.width);
                const snapY=ev.altKey?null:closestAlignment('y',moveY,bounds,indices,pageH/area.height);
                if(snapX)moveX=snapX.delta;
                if(snapY)moveY=snapY.delta;
                moveX=clamp(moveX,-bounds.minX,pageW-bounds.maxX);
                moveY=clamp(moveY,-bounds.minY,pageH-bounds.maxY);
                original.forEach(item=>{
                    elements[item.index].x_mm=round(item.x+moveX);
                    elements[item.index].y_mm=round(item.y+moveY);
                });
                setAlignmentGuide(guideX,'x',snapX);
                setAlignmentGuide(guideY,'y',snapY);
            }
            original.forEach(item=>{
                const e=elements[item.index];
                const box=$('canvas').querySelectorAll('.field')[item.index];
                if(!box)return;
                box.style.left=e.x_mm/pageW*100+'%';box.style.top=e.y_mm/pageH*100+'%';
                box.style.width=e.width_mm/pageW*100+'%';box.style.height=e.height_mm/pageH*100+'%';
                if(e.x_mm!==item.x || e.y_mm!==item.y || e.width_mm!==item.w || e.height_mm!==item.h)changed=true;
            });
            if(!isResize)syncSelectionOutline();
            const active=elements[selected];
            $('x').value=active.x_mm;$('y').value=active.y_mm;$('w').value=active.width_mm;$('h').value=active.height_mm;
        };
        let stopped=false;
        const stop=()=>{
            if(stopped)return;
            stopped=true;
            guideX?.remove();guideY?.remove();
            $('canvas').classList.remove('is-snapped-center');
            capture.removeEventListener('pointermove',move);
            capture.removeEventListener('pointerup',stop);
            capture.removeEventListener('pointercancel',stop);
            capture.removeEventListener('lostpointercapture',stop);
            if(changed){markDirty();flushHistory();}
            render();
        };
        capture.addEventListener('pointermove',move);
        capture.addEventListener('pointerup',stop);
        capture.addEventListener('pointercancel',stop);
        capture.addEventListener('lostpointercapture',stop);
    }
    const update=()=>{const e=elements[selected];if(!e)return;
        e.x_mm=round(clamp(Number($('x').value)||0,0,pageW-1));
        e.y_mm=round(clamp(Number($('y').value)||0,0,pageH-1));
        e.width_mm=round(clamp(Number($('w').value)||1,1,pageW-e.x_mm));
        e.height_mm=round(clamp(Number($('h').value)||1,1,pageH-e.y_mm));
        const family = $('fontFamily').value;
        const newStyle = ($('bold').checked?'B':'') + ($('italic').checked?'I':'');
        const custom = family.startsWith('installed_');
        const available = (window.CertifyFontVariants || {})[family] || {};
        const supported = !custom || !newStyle || !!available[newStyle];
        e.settings={...e.settings,font_family:family,font_size:clamp(Number($('fontSize').value)||32,6,110),align:$('align').value,color:$('color').value,bold:supported&&$('bold').checked,italic:supported&&$('italic').checked,auto_fit:$('autoFit').checked,literal:$('literal').value};
        markDirty();render();
    };
    const center=axis=>{
        const indices=selectionIndices();if(!indices.length)return;
        if(indices.length===1){
            const e=elements[selected];if(!e)return;
            if(axis==='x'){e.x_mm=round((pageW-e.width_mm)/2);e.settings.align='C';}
            else e.y_mm=round((pageH-e.height_mm)/2);
        }else{
            const bounds=selectionBounds(indices);
            const shift=axis==='x'?(pageW-bounds.minX-bounds.maxX)/2:(pageH-bounds.minY-bounds.maxY)/2;
            translate(indices,axis==='x'?shift:0,axis==='y'?shift:0);
        }
        markDirty();render();
    };
    for(const id of ['x','y','w','h','fontFamily','fontSize','align','color','bold','italic','autoFit'])$(id).addEventListener('change',update);
    // Visible push-button toggles keep the existing checkbox settings and PDF data format.
    for (const id of ['bold', 'italic']) {
        const button = $(id + 'Toggle');
        button.addEventListener('click', () => {
            const setting = $(id);
            if (setting.disabled) return;
            setting.checked = !setting.checked;
            setting.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
    $('literal').addEventListener('input',()=>{
        const e=elements[selected];
        if(!e||!['static_text','signatory_name','signatory_title'].includes(e.field_key))return;
        e.settings.literal=$('literal').value;
        $('literalCount').textContent=`${e.settings.literal.length} / 2000`;
        const field=$('canvas').querySelectorAll('.field')[selected]?.querySelector('.field-value');
        if(field){if(e.field_key==='static_text')previewInlineMarkup(field,e);else field.textContent=testText(e);renderText(field,e,$('canvas'));}
        markDirty();
    });
    const selectedParagraph=()=>elements[selected]?.field_key==='static_text'?elements[selected]:null;
    const refreshInlineParagraph=()=>{
        const e=selectedParagraph();if(!e)return;
        const field=$('canvas').querySelectorAll('.field')[selected]?.querySelector('.field-value');
        if(field){previewInlineMarkup(field,e);renderText(field,e,$('canvas'));}
    };
    document.querySelectorAll('[data-inline-token]').forEach(button=>button.addEventListener('click',()=>{
        const e=selectedParagraph();if(!e)return;
        const key=button.dataset.inlineToken;
        const value='{{'+key+'}}';
        const input=$('literal');const start=input.selectionStart??input.value.length;const end=input.selectionEnd??start;
        // Adding an inline award creates an ordinary, independently editable
        // award assignment source; its canvas layer is hidden by default.
        if(key==='AWARD'&&!elements.some(item=>item.field_key==='award_placement')){
            if(elements.length>=20){window.alert('Make room for an Award / Placement field before adding an inline award.');return;}
            const original=selected;
            addField('award_placement');
            elements[selected].settings.inline_only=true;
            selectSingle(original);render();
        }else if(key==='AWARD'){
            const award=elements.find(item=>item.field_key==='award_placement');
            award.settings.inline_only=true;
        }

        const next=input.value.slice(0,start)+value+input.value.slice(end);
        if(next.length>2000){window.alert('The paragraph limit is 2,000 characters.');return;}
        input.value=next;e.settings.literal=next;
        $('literalCount').textContent=`${next.length} / 2000`;
        input.focus({preventScroll:true});input.setSelectionRange(start+value.length,start+value.length);
        markDirty();refreshInlineParagraph();
        // Refresh the hidden award source without changing the active field.
        if(key==='AWARD')render();
    }));
    $('editorConfigureInlineAwards').addEventListener('click',()=>{
        const index=elements.findIndex(item=>item.field_key==='award_placement');
        if(index===-1){window.alert('Insert an Award value into the paragraph first.');return;}
        selectSingle(index);render();chooseTab('fields');
        $('editorAwardSettings').scrollIntoView({block:'nearest',behavior:'smooth'});
    });
    $('editorInlineEmphasis').addEventListener('change',()=>{
        const e=selectedParagraph();if(!e)return;
        e.settings.inline_emphasis=$('editorInlineEmphasis').checked;
        $('editorInlineColorRow').hidden=!e.settings.inline_emphasis;
        markDirty();refreshInlineParagraph();
    });
    $('editorInlineColor').addEventListener('input',()=>{
        const e=selectedParagraph();if(!e)return;
        e.settings.inline_color=$('editorInlineColor').value;
        markDirty();refreshInlineParagraph();
    });
    // Functional settings tabs; previous controls retain their original IDs and
    // are present in the DOM even when the panel is hidden.
    const tabButtons=Array.from(document.querySelectorAll('[data-editor-tab]'));
    const chooseTab=tabKey=>{
        tabButtons.forEach(button=>{
            const isActive=button.dataset.editorTab===tabKey;
            button.setAttribute('aria-selected',String(isActive));
            button.tabIndex=isActive?0:-1;
            const panel=document.querySelector(`[data-editor-panel="${button.dataset.editorTab}"]`);
            if(panel)panel.hidden=!isActive;
        });
    };
    tabButtons.forEach((button,i)=>{
        button.addEventListener('click',()=>chooseTab(button.dataset.editorTab));
        button.addEventListener('keydown',event=>{
            if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;
            event.preventDefault();
            const next=event.key==='Home'?0:event.key==='End'?tabButtons.length-1:(i+(event.key==='ArrowRight'?1:-1)+tabButtons.length)%tabButtons.length;
            tabButtons[next].focus();chooseTab(tabButtons[next].dataset.editorTab);
        });
    });
    chooseTab('fields');
    $('editorDetailsOpenStyle').addEventListener('click',()=>chooseTab('style'));
    $('selection').addEventListener('change',()=>{selectSingle(Number($('selection').value));render();});
    $('editorSelectAll').addEventListener('click',()=>{selectedFields=new Set(elements.map((_,i)=>i));render();});
    $('editorClearSelection').addEventListener('click',()=>{selectedFields=new Set([selected]);render();});
    $('editorGroupSelection').addEventListener('click',()=>{
        const indices=selectionIndices();if(indices.length<2)return;
        flushHistory();
        const groupId=`grp_${Date.now().toString(36)}_${(++groupSerial).toString(36)}`;
        indices.forEach(i=>{elements[i].settings.editor_group=groupId;});
        markDirty();saveHistory();render();
    });
    $('editorUngroupSelection').addEventListener('click',()=>{
        const ids=new Set(selectionIndices().map(groupIdFor).filter(Boolean));if(!ids.size)return;
        flushHistory();
        elements.forEach(e=>{if(ids.has(e.settings?.editor_group))delete e.settings.editor_group;});
        markDirty();saveHistory();render();
    });
    $('editorCopySelection').addEventListener('click',copySelectedFields);
    $('editorPasteSelection').addEventListener('click',pasteSelectedFields);
    $('editorUndo')?.addEventListener('click', undo);
    $('editorRedo')?.addEventListener('click', redo);
    document.addEventListener('keydown', event=>{
        const target=event.target;
        // Never intercept keys meant for text editing, native controls or links.
        if(target?.closest?.('input, textarea, select, [contenteditable], [role="textbox"]') || target?.isContentEditable) return;
        if(!previewMode && (event.key==='Backspace'||event.key==='Delete') &&
            !(event.ctrlKey||event.metaKey||event.altKey||event.shiftKey) &&
            !event.repeat && (!target?.closest?.('button, a, summary, [role="tab"]') ||
                target?.closest?.('#fieldList .editor-field-card'))){
            event.preventDefault();
            deleteSelectedFields();
            return;
        }
        if(!previewMode && event.key==='Escape' && selectedFields.size>1){event.preventDefault();selectedFields=new Set([selected]);render();return;}
        if(!(event.ctrlKey||event.metaKey) || event.altKey) return;
        if(!previewMode && event.key.toLowerCase()==='c' && !event.shiftKey){event.preventDefault();copySelectedFields();}
        else if(!previewMode && event.key.toLowerCase()==='v' && !event.shiftKey){event.preventDefault();pasteSelectedFields();}
        else if(event.key.toLowerCase()==='z'){event.preventDefault();event.shiftKey?redo():undo();}
        else if(event.key.toLowerCase()==='y'){event.preventDefault();redo();}
    });
    for(const id of ['centerX'])$(id).addEventListener('click',()=>center('x'));
    for(const id of ['centerY'])$(id).addEventListener('click',()=>center('y'));
    document.querySelectorAll('.editor-text-align-buttons button').forEach(button=>button.addEventListener('click',()=>{
        $('align').value=button.dataset.align;
        update();
    }));
    const changeCanvasMode = clean => {
        previewMode=Boolean(clean);
        document.querySelector('.certify-editor-page').classList.toggle('is-clean-preview',previewMode);
        $('editorEditMode').setAttribute('aria-pressed',String(!previewMode));
        $('editorCleanPreview').setAttribute('aria-pressed',String(previewMode));
        // Preview never touches field geometry or dirty/saved state.
        render();
    };
    $('editorEditMode').addEventListener('click',()=>changeCanvasMode(false));
    $('editorCleanPreview').addEventListener('click',()=>changeCanvasMode(true));
    $('canvasZoom').addEventListener('change',()=>{
        const requested=$('canvasZoom').value;
        zoomFactor=requested==='fit'?1:clamp(Number(requested)||1,1,1.5);
        render();
    });
    $('toggleCanvasFullscreen').addEventListener('click',()=>{
        const stage=$('editorStage');
        if(document.fullscreenElement===stage){document.exitFullscreen?.();return;}
        if(stage.requestFullscreen)stage.requestFullscreen().catch(()=>{});
    });
    document.addEventListener('fullscreenchange',render);

    $('sampleName').addEventListener('input',render);
    $('longName').addEventListener('click',()=>{$('sampleName').value='Maria Alexandra Dela Cruz-Santiago';render();});
    // Keyboard deletion applies atomically to the full selection. Keep one
    // recipient field, and do not leave orphan group IDs after removing fields.
    // Unlike a browser Backspace, this action is undoable in the editor.
    const deleteSelectedFields = () => {
        if(previewMode)return;
        const indices=selectionIndices();
        if(!indices.length)return;
        const selectedSet=new Set(indices);
        const survivors=elements.filter((_,index)=>!selectedSet.has(index));
        if(containsInlineAward() && !survivors.some(e=>e.field_key==='award_placement') && survivors.some(e=>e.field_key==='static_text' && /\{\{AWARD\}\}/i.test(e.settings.literal||''))){window.alert('This paragraph uses an inline Award. Remove the {{AWARD}} token before deleting its placement settings.');return;}
        if(!survivors.some(e=>e.field_key==='recipient_name')){
            window.alert('Keep at least one recipient name field. Deselect it before deleting the other fields.');
            return;
        }
        flushHistory();
        elements=survivors;
        // A group must have at least two fields. Clear any orphaned group IDs.
        const groupCounts=new Map();
        elements.forEach(e=>{const id=e.settings?.editor_group;if(id)groupCounts.set(id,(groupCounts.get(id)||0)+1);});
        elements.forEach(e=>{if(e.settings?.editor_group && groupCounts.get(e.settings.editor_group)<2)delete e.settings.editor_group;});
        selected=Math.min(indices[0],elements.length-1);
        selectedFields=new Set([selected]);
        markDirty();saveHistory();render();
    };
    const removeField = i => {
        if(elements.length===1 || (elements[i].field_key==='recipient_name' && elements.filter(e=>e.field_key==='recipient_name').length===1)) {
            window.alert('Keep at least one recipient name field.');return;
        }
        if(elements[i].field_key==='award_placement' && containsInlineAward()){window.alert('Remove the {{AWARD}} token from the paragraph before deleting its award settings.');return;}
        const removedGroup=groupIdFor(i);
        const previousSelection=selectionIndices();
        elements.splice(i,1);
        selectedFields=new Set(previousSelection.filter(j=>j!==i).map(j=>j>i?j-1:j));
        if(removedGroup){const members=elements.filter(e=>e.settings?.editor_group===removedGroup);if(members.length===1)delete members[0].settings.editor_group;}
        if(selected>i) selected--;
        else if(selected===i) selected=Math.min(i,elements.length-1);
        selectedFields=new Set([selected]);markDirty();render();
    };
    const addField = key => {
        if(elements.length >= 20){window.alert('A template can contain up to 20 fields.');return;}
        if(key==='certificate_number'||key==='award_placement'){
            const already=elements.findIndex(e=>e.field_key===key);
            if(already!==-1){selected=already;render();return;}
        }
        if(key==='e_signature'){
            if(elements.length+2>20){window.alert('A signatory block needs two available fields. The template limit is 20.');return;}
            const width=Math.min(72,pageW-24),height=Math.min(32,pageH*.16);
            const x=round((pageW-width)/2), y=round(clamp(pageH-height-39,8,pageH-height-29));
            const signature={field_key:key,kind:'signature',x_mm:x,y_mm:y,width_mm:width,height_mm:height,
                settings:{image_only:true}};
            elements.push(signature,makeSignatoryDetails(signature));
            selected=elements.length-2;selectedFields=new Set([selected]);markDirty();render();
            $('fieldList').querySelector('.is-selected')?.scrollIntoView({block:'nearest'});
            return;
        }
        const wide=key==='recipient_name';
        const width=round(Math.min(pageW-(wide?30:24),pageW*(wide?.76:key==='static_text'?.70:key==='award_placement'?.64:.56))); 
        const height=round(key==='static_text'?Math.min(42,pageH*.22):Math.min(wide?22:16,Math.max(10,pageH*(wide?.12:.075))));
        const y=key==='issued_date'?pageH*.24:key==='certificate_number'?pageH*.78:key==='award_placement'?pageH*.67:key==='static_text'?pageH*.62:pageH*.48;
        elements.push({
            field_key:key,kind:'text',x_mm:round((pageW-width)/2),y_mm:round(clamp(y,0,pageH-height)),width_mm:width,height_mm:height,
            settings:{font_size:wide?30:key==='certificate_number'?13:key==='award_placement'?24:18,font_family:wide?'dejavuserif':'dejavusans',align:'C',color:'#192A4B',bold:false,italic:false,auto_fit:true,literal:key==='static_text'?'Additional text':'',...(key==='award_placement'?{award_mode:'grouped',award_groups:JSON.stringify(defaultAwardGroups()),award_manual:''}:{})}
        });
        selected=elements.length-1;selectedFields=new Set([selected]);markDirty();render();
        const body=$('fieldList');body.querySelector('.is-selected')?.scrollIntoView({block:'nearest',behavior:'auto'});
    };
    document.querySelectorAll('[data-add-field]').forEach(button=>button.addEventListener('click',()=>{
        const key=button.dataset.addField;
        $('addFieldMenu').open=false;
        addField(key);
    }));
    $('saveForm').addEventListener('submit',()=>{flushHistory();dirty=false;updateSaveState();const root=$('hiddenFields');root.replaceChildren();const add=(name,value)=>{const input=document.createElement('input');input.type='hidden';input.name=name;input.value=String(value);root.append(input);};
        elements.forEach((e,i)=>{for(const key of ['field_key','kind','x_mm','y_mm','width_mm','height_mm'])add(`elements[${i}][${key}]`,e[key]);for(const [key,value]of Object.entries(e.settings||{})){if(key==='literal'&&!['static_text','signatory_name','signatory_title'].includes(e.field_key))continue;add(`elements[${i}][settings][${key}]`,typeof value==='boolean'?(value?'1':'0'):value);}});});
    $('discardEditorChanges')?.addEventListener('click',()=>{
        if(!dirty) return;
        if(!window.confirm('Discard your unsaved changes and restore the last saved version of this template?')) return;
        // The editor is loaded through GET, so refreshing restores the server-saved
        // template; no form POST is made and the original design is unchanged.
        dirty=false;
        Promise.resolve(window.CertifyDraftClear?.())
            .catch(() => window.alert('The backup could not be cleared. You may be offered recovery again.'))
            .finally(() => window.location.reload());
    });
    window.addEventListener('resize',render);
    if(typeof ResizeObserver!=='undefined')new ResizeObserver(refit).observe($('editorStage'));
    if(document.fonts?.ready)document.fonts.ready.then(refit);
    window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
    render();
    updateSaveState();
    if(hadDuplicateNumbers) $('saveState').textContent='Duplicate number fields removed — save changes';
})();
