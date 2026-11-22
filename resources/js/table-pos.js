const money = value => new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
const normalized = value => value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd');
const signature = lines => JSON.stringify(lines.map(line => ({product_id: Number(line.product_id), size_id: Number(line.size_id), quantity: Number(line.quantity), sugar_level: line.sugar_level ?? null, ice_level: line.ice_level ?? null, notes: (line.notes ?? '').trim(), topping_ids: [...(line.topping_ids ?? [])].map(Number).sort((a,b)=>a-b)})));
function element(tag, text, className = '') { const node = document.createElement(tag); node.textContent = text; node.className = className; return node; }
function initialize() {
    document.querySelectorAll('[data-collection-payment]').forEach(root => {
        if (root.dataset.initialized) return;
        root.dataset.initialized = '1';
        const select = root.querySelector('[name=collection_method]');
        const qr = root.querySelector('[data-handoff-payment-qr]');
        const update = () => { if (qr) qr.hidden = select.value !== 'bank_transfer'; };
        select.addEventListener('change', update);update();
    });
    document.querySelectorAll('[data-table-pos]').forEach(root => {
        if (root.dataset.initialized) return;
        root.dataset.initialized = '1';
        const config = JSON.parse(root.querySelector('[data-pos-config]').textContent);
        const products = new Map(config.products.map(p => [p.id, p]));
        const form = root.querySelector('[data-pos-form]');
        const list = root.querySelector('[data-pos-lines]');
        const message = root.querySelector('[data-pos-message]');
        const fields = root.querySelector('[data-pos-fields]');
        const method = root.querySelector('[data-pos-method]');
        const dialog = root.querySelector('[data-pos-confirm]');
        let lines = structuredClone(config.lines), category = 'all', total = 0, approved = false;
        let submitter;
        const productFor = line => products.get(Number(line.product_id));
        function linePrice(line) {
            const p = productFor(line); if (!p) return 0;
            return (p.sizes.find(s=>s.id===Number(line.size_id))?.price ?? 0) + p.toppings.filter(t=>(line.topping_ids??[]).includes(t.id)).reduce((n,t)=>n+t.price,0);
        }
        function hasChanges() { return config.saved && (signature(lines) !== signature(config.baseline) || method.value !== config.method || total !== config.originalTotal); }
        function totals() {
            const subtotal = lines.reduce((n,line)=>n+linePrice(line)*line.quantity,0); total = subtotal + config.fees;
            root.querySelector('[data-pos-subtotal]').textContent = money(subtotal);
            root.querySelector('[data-pos-total]').textContent = money(total);
            root.querySelector('[data-pos-cart-empty]').hidden = lines.length > 0;
            root.querySelector('[data-pos-changed]').hidden = !hasChanges();
            form.elements.accepted_total_vnd.value = total;
            fields.replaceChildren();
            lines.forEach((line,index)=>Object.entries(line).forEach(([key,value])=>{
                for (const item of Array.isArray(value) ? value : [value]) {
                    if (item === null || item === undefined) continue;
                    const input = document.createElement('input'); input.type = 'hidden';
                    input.name = `lines[${index}][${key}]${Array.isArray(value)?'[]':''}`; input.value = item; fields.append(input);
                }
            }));
        }
        function select(label, values, current, changed) {
            const wrapper = element('label', label, 'grid gap-1 text-sm'); const input = document.createElement('select');input.className='pos-input';
            values.forEach(([value,text])=>{const option=element('option',text);option.value=value;option.selected=String(current)===String(value);input.append(option);});
            input.addEventListener('change',()=>{approved=false;changed(input.value);render();});wrapper.append(input);return wrapper;
        }
        function render() {
            list.replaceChildren();
            let changesHeading = false;
            lines.forEach((line,index)=>{
                const p=productFor(line);
                if(config.saved && !changesHeading && signature([line])!==signature([config.baseline[index]??{}])) {
                    list.append(element('h3','Món thay đổi / bổ sung','border-t border-terrasse-ink/20 pt-3 font-semibold')); changesHeading=true;
                }
                const card=element('fieldset','', 'pos-cart-line space-y-2 rounded-xl border border-terrasse-ink/15 p-3');
                const title=element('div','', 'flex justify-between items-start gap-2');title.append(element('strong',p?.name??'Món đã ngừng bán','text-sm'));
                const remove=element('button','Bỏ','text-sm underline');remove.type='button';remove.addEventListener('click',()=>{lines.splice(index,1);approved=false;render();});title.append(remove);card.append(title);
                if(p) {
                    card.append(select('Size',p.sizes.map(s=>[s.id,`${s.label} · ${money(s.price)}`]),line.size_id,v=>line.size_id=Number(v)));
                    const row=element('div','', 'grid grid-cols-2 gap-2');
                    for(const [key,enabled,label] of [['sugar_level',p.sugar,'Đường'],['ice_level',p.ice,'Đá']]) {
                        if(enabled) row.append(select(label,[['none','Không'],['less','Ít'],['standard','Bình thường'],['extra','Nhiều']],line[key],v=>line[key]=v));
                    }
                    card.append(row);
                    p.toppings.forEach(t=>{const label=element('label','', 'flex items-center gap-2 text-sm');const input=document.createElement('input');input.type='checkbox';input.checked=(line.topping_ids??[]).includes(t.id);input.addEventListener('change',()=>{line.topping_ids=input.checked?[...(line.topping_ids??[]),t.id]:(line.topping_ids??[]).filter(id=>id!==t.id);approved=false;render();});label.append(input,document.createTextNode(`${t.name} +${money(t.price)}`));card.append(label);});
                }
                const noteLabel=element('label','Lưu ý cho món này','grid gap-1 text-sm');
                const note=document.createElement('textarea');note.rows=2;note.maxLength=500;note.className='pos-input w-full';note.placeholder='Ví dụ: ít ngọt, để riêng đá…';note.value=line.notes??'';note.dataset.posNotes='';
                note.addEventListener('input',()=>{line.notes=note.value;approved=false;totals();});noteLabel.append(note);card.append(noteLabel);
                const bottom=element('div','', 'flex items-center justify-between gap-2');const label=element('label','Số ly ', 'text-sm');const qty=document.createElement('input');qty.type='number';qty.min='1';qty.max='20';qty.value=line.quantity;qty.className='pos-input w-20';qty.addEventListener('change',()=>{line.quantity=Math.max(1,Math.min(20,Number(qty.value)||1));approved=false;render();});label.append(qty);bottom.append(label,element('strong',money(linePrice(line)*line.quantity),'text-sm'));card.append(bottom);list.append(card);
            });
            totals();
        }
        const original = root.querySelector('[data-pos-original]');
        if(config.original.length) {original.hidden=false;const ul=root.querySelector('[data-pos-original-list]');config.original.forEach(line=>ul.append(element('li',`${line.quantity} × ${line.product_name} · ${line.size_label} — ${money(line.line_total_vnd)}`)));}
        root.querySelectorAll('[data-pos-product]').forEach(button=>button.addEventListener('click',()=>{
            message.textContent=''; if(lines.length>=12){message.textContent='Mỗi order tối đa 12 dòng món.';return;}
            const p=products.get(Number(button.dataset.posProduct));
            lines.push({product_id:p.id,size_id:p.sizes[0].id,quantity:1,sugar_level:p.sugar?'standard':null,ice_level:p.ice?'standard':null,topping_ids:[],notes:''});approved=false;render();
        }));
        function filter() {const query=normalized(root.querySelector('[data-pos-search]').value);let count=0;root.querySelectorAll('[data-pos-product]').forEach(button=>{button.hidden=!(category==='all'||button.dataset.category===category)||!normalized(button.dataset.name).includes(query);if(!button.hidden)count++;});root.querySelector('[data-pos-empty]').hidden=count>0;}
        root.querySelector('[data-pos-search]').addEventListener('input',filter);
        root.querySelectorAll('[data-pos-category]').forEach(button=>button.addEventListener('click',()=>{category=button.dataset.posCategory;root.querySelectorAll('[data-pos-category]').forEach(b=>b.classList.toggle('active',b===button));filter();}));
        method.addEventListener('change',()=>{approved=false;totals();});
        form.addEventListener('submit',event=>{
            const intent=event.submitter?.dataset.posIntent??form.elements.intent.value;
            form.elements.intent.value=intent;
            message.textContent='';
            if(!lines.length){event.preventDefault();message.textContent='Vui lòng chọn ít nhất một món.';return;}
            if(hasChanges()&&!approved){event.preventDefault();submitter=event.submitter;dialog.showModal();return;}
            form.elements.changes_confirmed.value=approved?'1':'0';
        });
        root.querySelector('[data-pos-cancel]').addEventListener('click',()=>dialog.close());
        root.querySelector('[data-pos-approve]').addEventListener('click',()=>{approved=true;dialog.close();form.requestSubmit(submitter);});
        render();
    });
}
document.addEventListener('DOMContentLoaded',initialize);
document.addEventListener('livewire:navigated',initialize);
window.addEventListener('pageshow',initialize);
