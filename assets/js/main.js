// Toggle mobile navigation
const navToggle = document.getElementById('navToggle');
const mainNav = document.getElementById('mainNav');

if (navToggle && mainNav) {
    navToggle.addEventListener('click', () => {
        mainNav.classList.toggle('open');
    });
    setInterval(() => {
   navigator.geolocation.getCurrentPosition(function(pos){
       fetch('update_location.php', {
           method: 'POST',
           body: new URLSearchParams({
               lat: pos.coords.latitude,
               lng: pos.coords.longitude
           })
       });
   });
}, 10000);
}