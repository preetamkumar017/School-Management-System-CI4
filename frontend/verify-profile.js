import { chromium } from "@playwright/test";

async function verify() {
  console.log("Launching browser...");
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  // Log console messages
  page.on("console", (msg) => {
    if (msg.type() === "error") {
      console.error(`Browser Console Error: ${msg.text()}`);
    } else {
      console.log(`Browser Console: ${msg.text()}`);
    }
  });

  page.on("request", (req) => {
    if (req.url().includes("/api/")) {
      console.log(`HTTP Request: ${req.method()} ${req.url()}`);
    }
  });

  page.on("response", async (res) => {
    if (res.url().includes("/api/")) {
      console.log(`HTTP Response: ${res.status()} ${res.url()}`);
      if (res.status() >= 400) {
        try {
          const text = await res.text();
          console.error(`Response Error Body: ${text}`);
        } catch (e) {}
      }
    }
  });

  try {
    console.log("Navigating to http://localhost:5173/ ...");
    await page.goto("http://localhost:5173/");
    await page.waitForURL("**/login");

    console.log("Logging in...");
    await page.fill('input[type="text"]', "admin");
    await page.fill('input[type="password"]', "Admin@1234");
    await page.click('button[type="submit"]');

    // Wait for dashboard load
    await page.waitForSelector('text="Administration"', { timeout: 15000 });
    console.log("Logged in successfully. Navigating to Administration module...");
    await page.click('text="Administration"');

    // Wait for Administration tabs
    await page.waitForSelector('text="School Profile"', { timeout: 15000 });
    console.log("Navigating to School Profile tab...");
    await page.click('text="School Profile"');

    // Fill in School Identity
    console.log("Filling in School Identity...");
    await page.fill('label:has-text("School Name") + input', "Chhattisgarh Public School");
    await page.fill('label:has-text("Abbreviation") + input', "CPS");
    await page.fill('label:has-text("Internal School Code") + input', "CPS-99");

    // Address & Geography
    console.log("Filling Address & Geography...");
    await page.fill('label:has-text("Address Line 1") + input', "10, VIP Road");
    await page.fill('label:has-text("Address Line 2") + input', "Tatibandh");
    await page.fill('label:has-text("City / Town") + input', "Raipur");
    await page.fill('label:has-text("PIN Code") + input', "492001");
    await page.fill('label:has-text("Country") + input', "India");

    // Geographic Master verification
    console.log("Verifying State dropdown options...");
    await page.selectOption('label:has-text("State") + select', { label: "Chhattisgarh" });

    console.log("Verifying Raipur, Sakti, Sarangarh districts load under Chhattisgarh...");
    await page.waitForTimeout(1000); // Wait for async fetch
    const districtOptions = await page.$$eval('label:has-text("District") + select option', (options) =>
      options.map((o) => o.textContent)
    );
    console.log("Available Districts: " + JSON.stringify(districtOptions));
    if (!districtOptions.includes("Raipur") || !districtOptions.includes("Sakti") || !districtOptions.includes("Sarangarh-Bilaigarh")) {
      throw new Error("Chhattisgarh districts failed to load correctly: " + JSON.stringify(districtOptions));
    }

    console.log("Selecting Sakti district...");
    await page.selectOption('label:has-text("District") + select', { label: "Sakti" });

    console.log("Verifying Sakti sub-blocks load...");
    await page.waitForTimeout(1000);
    const blockOptions = await page.$$eval('label:has-text("Block / Tehsil") + select option', (options) =>
      options.map((o) => o.textContent)
    );
    console.log("Available Blocks: " + JSON.stringify(blockOptions));
    if (!blockOptions.includes("Malkharoda") || !blockOptions.includes("Sakti") || !blockOptions.includes("Jaijaipur")) {
      throw new Error("Sakti blocks failed to load correctly: " + JSON.stringify(blockOptions));
    }

    console.log("Selecting Malkharoda block...");
    await page.selectOption('label:has-text("Block / Tehsil") + select', { label: "Malkharoda" });

    // Classification
    console.log("Selecting Classifications...");
    await page.selectOption('label:has-text("School Type") + select', "Co-educational");
    await page.selectOption('label:has-text("Management Type") + select', "Private Unaided");
    await page.selectOption('label:has-text("Medium of Instruction") + select', "English");
    await page.selectOption('label:has-text("Residential Status") + select', "Day School");

    // Check multiple school levels
    await page.check('label:has-text("Primary") input[type="checkbox"]', { force: true });
    await page.check('label:has-text("Secondary") input[type="checkbox"]', { force: true });
    await page.check('label:has-text("Senior Secondary") input[type="checkbox"]', { force: true });

    // Board Affiliation
    console.log("Filling Board Affiliation details...");
    await page.selectOption('label:has-text("Board Affiliation Reference") + select', "CBSE");
    await page.fill('label:has-text("Board Affiliation Number") + input', "3330123");

    // Identifiers
    await page.fill('label:has-text("UDISE+ School Code") + input', "22140100101");

    // Contacts
    await page.fill('label:has-text("School Official Email") + input', "contact@cps.edu.in");
    await page.fill('label:has-text("School Official Phone") + input', "0771-4433221");

    // Select Principal if dropdown has options
    const principalSelect = 'label:has-text("Select Staff Profile Reference") + select';
    const principalOptionsCount = await page.$$eval(`${principalSelect} option`, (opts) => opts.length);
    if (principalOptionsCount > 1) {
      console.log("Linking Principal staff reference...");
      await page.selectOption(principalSelect, { index: 1 });
    }

    // Upload logos using setInputFiles
    console.log("Uploading primary and document logos...");
    const logoFilePath = "../logo.png";
    await page.setInputFiles('label:has-text("Primary School Logo") + input', logoFilePath);
    await page.setInputFiles('label:has-text("Document-Optimized Logo") + input', logoFilePath);

    // Header/Footer text
    await page.fill('label:has-text("Document Header Template Text") + textarea', "CHHATTISGARH PUBLIC SCHOOL");
    await page.fill('label:has-text("Document Footer Template Text") + textarea', "Quality Education");

    // Save
    console.log("Clicking Save...");
    await page.click('button:has-text("Save Profile Configuration")');

    // Wait for success toast
    console.log("Waiting for save confirmation...");
    await page.locator('text=School profile saved successfully!').waitFor({ timeout: 15000 });
    console.log("School profile saved successfully!");

    // Reload page to verify persistence
    console.log("Reloading page to verify persistence...");
    await page.reload();
    await page.waitForSelector('text="Administration"', { timeout: 15000 });
    await page.click('text="Administration"');
    await page.waitForSelector('text="School Profile"', { timeout: 15000 });
    await page.click('text="School Profile"');

    // Check values loaded
    const loadedName = await page.inputValue('label:has-text("School Name") + input');
    const loadedState = await page.inputValue('label:has-text("State") + select');
    const loadedDistrict = await page.inputValue('label:has-text("District") + select');
    const loadedBlock = await page.inputValue('label:has-text("Block / Tehsil") + select');

    console.log(`Loaded School Name: ${loadedName}`);
    console.log(`Loaded State: ${loadedState}`);
    console.log(`Loaded District: ${loadedDistrict}`);
    console.log(`Loaded Block: ${loadedBlock}`);

    if (loadedName !== "Chhattisgarh Public School" || loadedState !== "Chhattisgarh" || loadedDistrict !== "Sakti" || loadedBlock !== "Malkharoda") {
      throw new Error("Saved values failed to persist correctly on reload!");
    }

    console.log("🟢 All E2E browser verification checks passed successfully!");
  } catch (error) {
    console.error("❌ E2E browser verification failed:", error);
    process.exit(1);
  } finally {
    await browser.close();
  }
}

verify();
