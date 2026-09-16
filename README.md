## Document Request System

A web-based platform that lets URS - Morong students request registrar documents — such as a Certificate of Registration (COR), Certificate of Grades (COG), Prospectus, and 
Diploma — online, instead of lining up in person at the registrar's office.



## How it works
1. **Fill up the form** — enter your details. Instant and no account is needed.
2. **Save your receipt** — print your claim receipt, showing your claim date and what you'll need to track your request.
3. **Pay at the cashier** — pay the processing fee at the Cashier's Office and keep the official receipt.
4. **Registrar processes it** — the registrar's office reviews and prepares your document.
5. **Claim your document** — once marked "Ready for Pickup," bring your receipt and a valid ID to claim it.



## Project structure
```
Request-System/
├── admin/              # Admin-side tools for managing and processing requests
├── assets/             # Static assets (styles, images, scripts)
├── config/             # App / database configuration
├── database/           # Database schema and/or seed files
├── includes/           # Shared PHP partials (header, footer, etc.)
├── index.php           # Homepage
├── request.php         # Document request form
├── submit_request.php  # Handles form submission
├── track.php           # Track the status of a submitted request
└── receipt.php         # Printable claim receipt
```



## Tech stack
- **PHP** — server-side logic and page rendering
- **MySQL** — data storage
- **HTML/CSS/JS** — front end



## Requests lifecycle
Each request submitted through `request.php` can later be looked up on `track.php` using the details provided at submission, and a receipt for it can be 
viewed/printed via `receipt.php`. Administrators manage and update request status from the `admin/` section.



## Getting started

1. Clone the repository:
   ```bash
   git clone https://github.com/sepgb/Request-System.git
   ```
2. Set up a local PHP + MySQL environment (e.g. XAMPP, WAMP, or MAMP).
3. Import the schema/seed files from the `database/` folder into your MySQL server.
4. Update the database connection details in `config/` to match your local setup.
5. Place the project folder in your server's document root (e.g. `htdocs/`) and open `index.php` in your browser.
