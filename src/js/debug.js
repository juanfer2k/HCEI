// Debug initialization
window.addEventListener('load', function() {
    console.log('Debug Info:');
    console.log('jQuery available:', typeof jQuery !== 'undefined');
    console.log('Select2 plugin:', typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined');
    console.log('AppForm namespace:', window.AppForm);
    console.log('Theme variables:', {
        '--color-tab': getComputedStyle(document.documentElement).getPropertyValue('--color-tab'),
        '--color-tam': getComputedStyle(document.documentElement).getPropertyValue('--color-tam'),
    });
});