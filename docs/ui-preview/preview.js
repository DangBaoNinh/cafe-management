const themes = {
    phin: {
        label: 'Cà phê pha phin',
        title: ['Chậm lại,', 'thơm cả ngày.'],
        description: 'Một ly cà phê phin đậm đà, chút sữa ngọt và thật nhiều đá. Chọn món bạn thích cho hôm nay.',
        image: 'phin.jpg',
        alt: 'Cà phê sữa đá pha bằng phin',
        caption: 'Cà phê sữa đá',
        color: '#f9f4ea',
    },
    pho: {
        label: 'Cà phê và trà',
        title: ['Hẹn nhau', 'một ly nhé!'],
        description: 'Bạc xỉu béo nhẹ, cà phê sữa đậm vị hay matcha mát lạnh. Hôm nay bạn muốn uống gì?',
        image: 'bac-xiu-hero.jpg',
        alt: 'Ly bạc xỉu nhiều đá trên nền hồng san hô',
        caption: 'Bạc xỉu',
        color: '#fff4ee',
    },
    rang: {
        label: 'Cà phê rang đậm',
        title: ['Đậm vị,', 'thơm lâu.'],
        description: 'Nếu thích hương rang và vị đắng nhẹ, hãy bắt đầu với cà phê phin của quán.',
        image: 'dam-dem.jpg',
        alt: 'Tách cà phê nóng trên bàn gỗ sẫm màu',
        caption: 'Cà phê phin nóng',
        color: '#171310',
    },
};

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
    const theme = Object.hasOwn(themes, requestedTheme) ? requestedTheme : 'phin';
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
    document.querySelector('#hero-title').replaceChildren(content.title[0], document.createElement('br'), content.title[1]);
    document.querySelector('#hero-description').textContent = content.description;
    document.querySelector('#hero-caption').textContent = content.caption;
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
    document.querySelector('#products-label').hidden = isMenu;
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
