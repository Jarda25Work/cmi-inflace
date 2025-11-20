/**
 * Search Autocomplete
 */
class SearchAutocomplete {
    constructor(inputElement, suggestionsContainer) {
        this.input = inputElement;
        this.container = suggestionsContainer;
        this.selectedIndex = -1;
        this.suggestions = [];
        this.debounceTimer = null;
        
        this.init();
    }
    
    init() {
        // Input event s debounce
        this.input.addEventListener('input', (e) => {
            clearTimeout(this.debounceTimer);
            const query = e.target.value.trim();
            
            if (query.length < 2) {
                this.hideSuggestions();
                return;
            }
            
            this.debounceTimer = setTimeout(() => {
                this.fetchSuggestions(query);
            }, 300);
        });
        
        // Focus event - znovu zobraz návrhy pokud existují
        this.input.addEventListener('focus', (e) => {
            const query = e.target.value.trim();
            if (query.length >= 2 && this.suggestions.length > 0) {
                this.container.classList.add('active');
            }
        });
        
        // Klávesové zkratky
        this.input.addEventListener('keydown', (e) => {
            // Pokud jsou návrhy aktivní, preventDefault pro Enter
            if (this.container.classList.contains('active')) {
                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        this.selectNext();
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        this.selectPrevious();
                        break;
                    case 'Enter':
                        e.preventDefault();
                        if (this.selectedIndex >= 0) {
                            this.selectCurrent();
                        } else {
                            // Pokud není vybrána žádná položka, zavři návrhy a odešli formulář
                            this.hideSuggestions();
                            e.target.form.submit();
                        }
                        break;
                    case 'Escape':
                        e.preventDefault();
                        this.hideSuggestions();
                        break;
                }
            }
        });
        
        // Zavřít při kliku mimo
        document.addEventListener('click', (e) => {
            if (!this.input.contains(e.target) && !this.container.contains(e.target)) {
                this.hideSuggestions();
            }
        });
    }
    
    async fetchSuggestions(query) {
        try {
            const response = await fetch(`api/search_suggestions.php?q=${encodeURIComponent(query)}`);
            
            if (!response.ok) {
                throw new Error('Failed to fetch suggestions');
            }
            
            const data = await response.json();
            this.suggestions = data;
            this.renderSuggestions(query);
        } catch (error) {
            console.error('Autocomplete error:', error);
            this.hideSuggestions();
        }
    }
    
    renderSuggestions(query) {
        if (this.suggestions.length === 0) {
            this.container.innerHTML = '<div class="autocomplete-no-results">Žádné výsledky</div>';
            this.container.classList.add('active');
            return;
        }
        
        const escapedQuery = this.escapeHtml(query);
        const regex = new RegExp(`(${this.escapeRegex(query)})`, 'gi');
        
        const html = this.suggestions.map((item, index) => {
            const highlightedText = this.escapeHtml(item.text).replace(
                regex, 
                '<span class="autocomplete-highlight">$1</span>'
            );
            
            return `
                <div class="autocomplete-item" data-index="${index}" data-text="${this.escapeHtml(item.text)}">
                    <div class="autocomplete-item-title">${highlightedText}</div>
                    <div class="autocomplete-item-meta">${item.type}</div>
                </div>
            `;
        }).join('');
        
        this.container.innerHTML = html;
        this.container.classList.add('active');
        this.selectedIndex = -1;
        
        // Přidat event listenery
        this.container.querySelectorAll('.autocomplete-item').forEach(item => {
            item.addEventListener('click', () => {
                this.selectItem(parseInt(item.dataset.index));
            });
        });
    }
    
    selectNext() {
        if (this.selectedIndex < this.suggestions.length - 1) {
            this.selectedIndex++;
            this.updateSelection();
        }
    }
    
    selectPrevious() {
        if (this.selectedIndex > 0) {
            this.selectedIndex--;
            this.updateSelection();
        }
    }
    
    updateSelection() {
        const items = this.container.querySelectorAll('.autocomplete-item');
        items.forEach((item, index) => {
            if (index === this.selectedIndex) {
                item.classList.add('selected');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('selected');
            }
        });
    }
    
    selectCurrent() {
        if (this.selectedIndex >= 0) {
            this.selectItem(this.selectedIndex);
        }
    }
    
    selectItem(index) {
        const item = this.suggestions[index];
        if (item) {
            // Doplň text do vstupního pole a odešli formulář pro vyhledání
            this.input.value = item.text;
            this.hideSuggestions();
            this.input.form.submit();
        }
    }
    
    hideSuggestions() {
        this.container.classList.remove('active');
        this.selectedIndex = -1;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    escapeRegex(text) {
        return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
}

// Inicializace při načtení stránky
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const suggestionsContainer = document.getElementById('searchSuggestions');
    
    if (searchInput && suggestionsContainer) {
        new SearchAutocomplete(searchInput, suggestionsContainer);
    }
});
