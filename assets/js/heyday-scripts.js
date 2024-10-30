document.addEventListener("DOMContentLoaded", function () {
  var redirectButton = document.getElementById("heyday-redirect-button");

  if (redirectButton) {
    redirectButton.addEventListener("click", function () {
      var redirectUrl = document.getElementById("heyday-redirect-url").value;
      window.open(redirectUrl, "_blank");
    });
  }
});
