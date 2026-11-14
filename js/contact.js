/* CONTACT US PAGE - SA'ADATUL D.I.Y
   Submits the enquiry to api/contact.php which saves it in
   MySQL (contact_messages table). Success shows only after
   the server confirms the save. */

const contactForm = document.getElementById("contactForm");
const contactMessage = document.getElementById("contactMessage");

// Brunei phone: customers type only the 7 local digits;
// the grey +673 prefix is fixed in the HTML.
function enforceContactPhone(input) {
    input.value = input.value.replace(/\D/g, "").slice(0, 7);
}

function showContactMessage(text, type) {
    if (!contactMessage) return;
    contactMessage.textContent = text;
    contactMessage.className = "form-message " + type;
}

contactForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    const fullName = document.getElementById("your-name").value.trim();
    const email = document.getElementById("your-email").value.trim();
    const phoneDigits = document.getElementById("phone-number").value.replace(/\D/g, "");
    const message = document.getElementById("message").value.trim();

    // ---- Client-side validation (server validates again) ----
    let firstError = null;

    if (fullName.length < 2) {
        firstError = "Please enter your name.";
        document.getElementById("your-name").focus();
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        firstError = "Please enter a valid email address.";
        document.getElementById("your-email").focus();
    } else if (phoneDigits.length !== 7) {
        firstError = "Please enter a valid Brunei phone number (7 digits after +673).";
        document.getElementById("phone-number").focus();
    } else if (!message) {
        firstError = "Please enter your message.";
        document.getElementById("message").focus();
    }

    if (firstError) {
        showContactMessage(firstError, "error");
        return;
    }

    const button = contactForm.querySelector("button[type='submit']");
    button.disabled = true;
    button.textContent = "Sending...";

    try {
        const result = await SDIY.api("contact.php", {
            method: "POST",
            body: {
                fullname: fullName,
                email: email,
                phone: "+673" + phoneDigits,   // full international format
                message: message
            }
        });

        contactForm.reset();
        showContactMessage(result.message, "success");

    } catch (error) {
        showContactMessage(error.message, "error");
    } finally {
        button.disabled = false;
        button.textContent = "Send";
    }
});
