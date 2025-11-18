// Globální funkce pro práci s API
const API = {
    baseUrl: 'api/',
    
    async get(endpoint, params = {}) {
        const url = new URL(this.baseUrl + endpoint, window.location.origin);
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
        
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    },
    
    async post(endpoint, data) {
        const response = await fetch(this.baseUrl + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    }
};

// Utility funkce
const Utils = {
    formatCena(cena) {
        if (cena === null || cena === undefined) {
            return '-';
        }
        const rounded = Math.round(Number(cena));
        return new Intl.NumberFormat('cs-CZ', {
            style: 'currency',
            currency: 'CZK',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(rounded);
    },
    
    formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('cs-CZ');
    },
    
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// Search handling
document.addEventListener('DOMContentLoaded', () => {
    const searchForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('searchInput');
    
    if (searchForm && searchInput) {
        // Live search s debounce
        const handleSearch = Utils.debounce(() => {
            searchForm.submit();
        }, 500);
        
        searchInput.addEventListener('input', handleSearch);
    }
    
    // Confirm dialogs pro delete akce
    const deleteButtons = document.querySelectorAll('.btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            if (!confirm('Opravdu chcete smazat tuto položku?')) {
                e.preventDefault();
            }
        });
    });
});

// Pro editační formulář - validace
if (document.getElementById('editForm')) {
    const editForm = document.getElementById('editForm');
    
    editForm.addEventListener('submit', (e) => {
        // Základní validace
        const requiredFields = editForm.querySelectorAll('[required]');
        let valid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                valid = false;
            } else {
                field.classList.remove('error');
            }
        });
        
        if (!valid) {
            e.preventDefault();
            alert('Vyplňte všechna povinná pole.');
        }
    });
}

// Pro kalkulaci cen v reálném čase (na editační stránce)
const CenyCalculator = {
    async vypocitejCeny(meridloId, odRoku, doRoku) {
        try {
            const data = await API.get('vypocitej_ceny.php', {
                meridlo_id: meridloId,
                od_roku: odRoku,
                do_roku: doRoku
            });
            return data;
        } catch (error) {
            console.error('Chyba při výpočtu cen:', error);
            return null;
        }
    },
    
    displayCeny(ceny) {
        const container = document.getElementById('cenyPreview');
        if (!container) return;
        
        let html = '<table class="gov-table"><thead><tr><th>Rok</th><th>Cena</th></tr></thead><tbody>';
        
        for (const [rok, cena] of Object.entries(ceny)) {
            html += `<tr><td>${rok}</td><td>${Utils.formatCena(cena)}</td></tr>`;
        }
        
        html += '</tbody></table>';
        container.innerHTML = html;
    }
};

console.log('CMI Inflace system loaded');