function toggleMobileMenu() {
  const menu = document.getElementById("mobile-menu");
  const icon = document.getElementById("menu-icon");

  if (menu && icon) {
    const isHidden = menu.classList.contains("hidden");

    if (isHidden) {
      menu.classList.remove("hidden");
      // Swap the hamburger icon out for an "X" close mark
      icon.classList.remove("fa-bars");
      icon.classList.add("fa-xmark");
    } else {
      menu.classList.add("hidden");
      // Swap the "X" close mark back to the hamburger icon
      icon.classList.remove("fa-xmark");
      icon.classList.add("fa-bars");
    }
  }
}

document.addEventListener("DOMContentLoaded", function () {
  const phoneInput = document.querySelector("#phone");
  const form = document.querySelector("form");
  const fullPhoneHidden = document.querySelector("#full_phone");

  let iti = null;
  if (phoneInput) {
    iti = window.intlTelInput(phoneInput, {
      initialCountry: "in",
      preferredCountries: [
        "in",
        "sa",
        "ae",
        "qa",
        "om",
        "kw",
        "bh",
        "gb",
        "us",
      ],
      separateDialCode: true,
      autoPlaceholder: "polite",
      utilsScript:
        "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js",
    });
  }

  // Intercept form submit to bind the full country code number
  if (form && iti) {
    form.addEventListener("submit", function (event) {
      // Get the complete number including country dial code (e.g., +919698844412)
      const fullNumber = iti.getNumber();

      // Set it to the hidden input that the PHP script is actually looking for
      fullPhoneHidden.value = fullNumber;
    });
  }

  // Listen for submission URL responses to unhide status flags elegantly
  const params = new URLSearchParams(window.location.search);
  if (params.get("status") === "success") {
    const successBox = document.getElementById("success-message");
    if (successBox) successBox.classList.remove("hidden");
    cleanURL();
  } else if (params.get("status") === "error") {
    const errorBox = document.getElementById("error-message");
    const errorText = document.getElementById("error-text");
    if (errorBox) {
      if (params.has("msg")) {
        errorText.textContent = decodeURIComponent(params.get("msg"));
      }
      errorBox.classList.remove("hidden");
    }
    cleanURL();
  }

  function cleanURL() {
    const cleanUrl =
      window.location.protocol +
      "//" +
      window.location.host +
      window.location.pathname;
    window.history.replaceState({ path: cleanUrl }, "", cleanUrl);
  }
});
