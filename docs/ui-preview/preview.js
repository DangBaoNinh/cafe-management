const themes = {
    terrasse: {
        label: 'Cà phê theo phong cách Paris',
        title: ['Một góc', 'Paris.'],
        description: 'Dành một chút thời gian cho ly cà phê yêu thích, như ngồi bên hiên quán một sáng ở Paris.',
        image: 'paris-terrasse.jpg',
        alt: 'Tách cà phê và bánh sừng bò trên bàn đá bên hiên quán Paris',
        color: '#f6efe3',
    },
    patisserie: {
        label: 'Cảm hứng từ tiệm bánh Pháp',
        title: ['Một sáng', 'thật dịu.'],
        description: 'Ánh nắng qua cửa sổ và một ly bạc xỉu béo nhẹ. Bạn muốn bắt đầu ngày mới với món gì?',
        image: 'paris-patisserie.jpg',
        alt: 'Bánh sừng bò và cà phê sữa trên khăn vải dưới nắng sớm',
        color: '#faf0ea',
    },
    salon: {
        label: 'Cà phê sau hoàng hôn',
        title: ['Một tối', 'chậm rãi.'],
        description: 'Khi phố lên đèn, nhấp một ngụm cà phê đậm vị và để câu chuyện kéo dài thêm một chút.',
        image: 'paris-salon.jpg',
        alt: 'Tách espresso trên bàn đá xanh bên ngọn đèn vàng',
        color: '#17251f',
    },
};

const previousThemes = { phin: 'terrasse', pho: 'patisserie', rang: 'salon' };

const descriptions = {
    'ca-phe-sua': 'Cà phê phin đậm vị kết hợp với sữa đặc và đá.',
    'bac-xiu': 'Nhiều sữa, một chút cà phê và đá. Vị ngọt béo, hương cà phê nhẹ.',
    'matcha': 'Matcha hòa cùng sữa tươi, dùng lạnh với đá.',
};

const root = document.documentElement;
const themeLinks = [...document.querySelectorAll('[data-theme-link]')];
const pageLinks = [...document.querySelectorAll('[data-page]')];
const categories = [...document.querySelectorAll('.categories button')];
const products = [...document.querySelectorAll('[data-drink]')];
const search = document.querySelector('#drink-search');
const hero = document.querySelector('.hero');
const heroImage = document.querySelector('#hero-image');
const dialog = document.querySelector('#drink-dialog');

let selectedCategory = 'all';
let previousPage;

function normalizeSearch(value) {
    return value.toLocaleLowerCase('vi').normalize('NFD').replace(/\p{Mark}/gu, '').replaceAll('đ', 'd').trim();
}

function filterProducts() {
    const query = normalizeSearch(search.value);
    const isMenu = root.dataset.pageView === 'menu';

    for (const product of products) {
        const matchesCategory = selectedCategory === 'all' || product.dataset.category === selectedCategory;
        const matchesQuery = normalizeSearch(product.querySelector('strong').textContent).includes(query);
        product.hidden = isMenu && !(matchesCategory && matchesQuery);
    }

    document.querySelector('#empty-results').hidden = products.some((product) => !product.hidden);
}

function showPage() {
    const [requestedTheme, requestedPage] = window.location.hash.slice(1).split('/');
    const currentTheme = Object.hasOwn(previousThemes, requestedTheme) ? previousThemes[requestedTheme] : requestedTheme;
    const theme = Object.hasOwn(themes, currentTheme) ? currentTheme : 'terrasse';
    const page = requestedPage === 'thuc-don' ? 'menu' : 'home';
    const isMenu = page === 'menu';
    const content = themes[theme];

    root.dataset.theme = theme;
    root.dataset.pageView = page;
    document.querySelector('meta[name="theme-color"]').content = content.color;
    document.title = `Coffee Shop | ${isMenu ? 'Thực đơn' : 'Trang chủ'}`;

    for (const link of themeLinks) {
        link.href = `#${link.dataset.themeLink}${isMenu ? '/thuc-don' : ''}`;
        if (link.dataset.themeLink === theme) link.setAttribute('aria-current', 'true');
        else link.removeAttribute('aria-current');
    }

    for (const link of pageLinks) {
        link.href = `#${theme}${link.dataset.page === 'menu' ? '/thuc-don' : ''}`;
        if (link.closest('nav')) {
            if (link.dataset.page === page) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        }
    }

    document.querySelector('#hero-label').textContent = content.label;
    document.querySelector('#hero-title-main').textContent = content.title[0];
    document.querySelector('#hero-title-accent').textContent = content.title[1];
    document.querySelector('#hero-description').textContent = content.description;
    heroImage.src = `images/${content.image}`;
    heroImage.alt = content.alt;
    hero.hidden = isMenu;

    // Trang thực đơn có tiêu đề chính riêng vì hero được ẩn.
    const headingTag = isMenu ? 'h1' : 'h2';
    let heading = document.querySelector('#products-title');
    if (heading.localName !== headingTag) {
        const replacement = document.createElement(headingTag);
        replacement.id = heading.id;
        heading.replaceWith(replacement);
        heading = replacement;
    }
    heading.textContent = isMenu ? 'Thực đơn' : 'Món được yêu thích';
    document.querySelector('#all-drinks').hidden = isMenu;
    document.querySelector('#menu-controls').hidden = !isMenu;
    filterProducts();

    if (previousPage && previousPage !== page) {
        const pageHeading = document.querySelector(isMenu ? '#products-title' : '#hero-title');
        pageHeading.tabIndex = -1;
        pageHeading.focus({ preventScroll: true });
        window.scrollTo(0, 0);
    }
    previousPage = page;
}

for (const category of categories) {
    category.addEventListener('click', () => {
        selectedCategory = category.dataset.category;
        for (const button of categories) {
            button.setAttribute('aria-pressed', String(button === category));
        }
        filterProducts();
    });
}

for (const product of products) {
    product.querySelector('button').addEventListener('click', () => {
        const title = product.querySelector('strong').textContent;
        const drinkImage = document.querySelector('#drink-image');
        drinkImage.src = product.querySelector('img').src;
        drinkImage.alt = title;
        document.querySelector('#drink-title').textContent = title;
        document.querySelector('#drink-description').textContent = descriptions[product.dataset.drink];
        document.querySelector('#drink-price').textContent = product.querySelector('.product-price').textContent;
        dialog.showModal();
    });
}

search.addEventListener('input', filterProducts);
window.addEventListener('hashchange', showPage);
showPage();
