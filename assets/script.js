  document.addEventListener("DOMContentLoaded", function () {
    function toggleByLocation(location) {
      const isIndia = location.toLowerCase() === 'india';
      const indiaEl = document.getElementById('india');
      const notIndiaEl = document.getElementById('not-india');

      if (indiaEl && notIndiaEl) {
        indiaEl.style.display = isIndia ? 'flex' : 'none';
        notIndiaEl.style.display = isIndia ? 'none' : 'flex';
        console.log("Detected country:", location);
      } else {
        console.warn("One or both elements not found in DOM");
      }
    }

    // Fetch location using free IP geolocation API
    fetch('https://ipapi.co/json/')
      .then(res => res.json())
      .then(data => {
        if (data && data.country_name) {
          toggleByLocation(data.country_name);
        } else {
          console.warn("Could not detect country");
        }
      })
      .catch(err => {
        console.error("Location API error:", err);
      });
  });

 document.addEventListener("DOMContentLoaded", function () {
    const changeLink = document.getElementById("pmpro_actionlink-change");
    if (changeLink) {
      changeLink.setAttribute("href", "/pricing");
    }
  });
