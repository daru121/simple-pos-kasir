document.addEventListener('DOMContentLoaded', function() {
    // Get the filter toggle button and filter section
    const filterToggleBtn = document.querySelector('#filterToggleBtn');
    const filterSection = document.querySelector('#filterSection');
    const productListSection = document.querySelector('#productListSection');
    const productGrid = document.querySelector('#productGrid');

    // Set initial state
    let isExpanded = true;

    // Add click event listener to the button
    filterToggleBtn.addEventListener('click', function() {
        isExpanded = !isExpanded;
        
        if (isExpanded) {
            // Expand view
            filterSection.classList.remove('d-none');
            productGrid.classList.remove('minimized');
            filterToggleBtn.textContent = 'Sembunyikan Filter';
            
            // Reset product cards to normal size
            const productCards = document.querySelectorAll('.product-card');
            productCards.forEach(card => {
                card.classList.remove('col-md-2');
                card.classList.add('col-md-3');
            });
        } else {
            // Minimize view
            filterSection.classList.add('d-none');
            productGrid.classList.add('minimized');
            filterToggleBtn.textContent = 'Tampilkan Filter';
            
            // Make product cards smaller
            const productCards = document.querySelectorAll('.product-card');
            productCards.forEach(card => {
                card.classList.remove('col-md-3');
                card.classList.add('col-md-2');
            });
        }
    });
});