document
  .getElementById("createChannelBtn")
  .addEventListener("click", function () {
    const name = document.getElementById("channelName").value.trim();
    const description = document
      .getElementById("channelDescription")
      .value.trim();
    const visibility =
      document.querySelector('input[name="visibility"]:checked')?.value ||
      "public";
    const category = document.getElementById("channelType").value;

    if (!name) {
      alert("Please enter a channel name.");
      return;
    }

    // Disable button to prevent double submission
    const btn = this;
    btn.disabled = true;
    btn.textContent = "Creating...";

    // Prepare data – define formData FIRST
    const formData = new URLSearchParams();
    formData.append("name", name);
    formData.append("description", description);
    formData.append("visibility", visibility);
    formData.append("category", category);
    // formData.append('barangay_id', ''); // optional

    fetch("../actions/create_channel.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: formData.toString(),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          const message = encodeURIComponent("Channel created successfully!");
          parent.location.href =
            "../message.php?toast=" + message + "&type=success";
        } else {
          alert(data.error || "Failed to create channel.");
          btn.disabled = false;
          btn.textContent = "Create";
        }
      })
      .catch((err) => {
        console.error(err);
        alert("Network error. Please try again.");
        btn.disabled = false;
        btn.textContent = "Create";
      });
  });
