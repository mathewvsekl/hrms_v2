const { test, expect } = require('@playwright/test');

test.use({
  storageState: async ({ browser }, use) => {
    await use({
      cookies: [],
      origins: [
        {
          origin: 'https://emm.anedins.com',
          localStorage: [
            {
              name: 'hrms_auth_token',
              value: '485a656aaebb7a1494dfb34ee7ee8188236da86e1f60be5a5812266e02bb412c'
            },
            {
              name: 'hrms_user',
              value: JSON.stringify({
                email: 'mathew.vsekl@gmail.com',
                user_id: 1,
                role: 'SuperAdmin',
                username: 'mathew.vsekl@gmail.com'
              })
            }
          ]
        }
      ]
    });
  }
});

test('Functional Flow: Create New Employee Draft', async ({ page }) => {
  test.setTimeout(60000); // 60 seconds

  const uniqueId = Date.now().toString().slice(-6);
  const firstName = `TestFunc`;
  const lastName = `User${uniqueId}`;
  const testEmail = `test.user${uniqueId}@example.com`;

  console.log('1. Navigating to Onboarding page...');
  await page.goto('https://emm.anedins.com/onboarding', { waitUntil: 'networkidle' });

  console.log('2. Starting New Onboarding...');
  await page.getByRole('button', { name: /Start New Onboarding/i }).click();

  // STEP 1
  console.log('3. Filling out Personal Data (Step 1)...');
  await page.waitForSelector('text=Personal Data');
  
  await page.locator('xpath=//label[contains(text(), "First Name *")]/following-sibling::input').fill(firstName);
  await page.locator('xpath=//label[contains(text(), "Last Name *")]/following-sibling::input').fill(lastName);
  await page.locator('xpath=//label[contains(text(), "Work Email *")]/following-sibling::input').fill(testEmail);

  await page.getByRole('button', { name: /Next/i }).click();

  // STEP 2
  console.log('4. Filling out Employment Details (Step 2)...');
  await page.waitForSelector('text=Employment Details');
  
  const companySelect = page.locator('xpath=//label[contains(text(), "Primary Company *")]/following-sibling::select');
  const companyOptions = await companySelect.locator('option').allInnerTexts();
  if (companyOptions.length > 1) await companySelect.selectOption({ index: 1 });

  const hireDateInput = page.locator('xpath=//label[contains(text(), "Hire Date *")]/following-sibling::div//input').first();
  await hireDateInput.fill('15062026');

  const roleSelect = page.locator('xpath=//label[contains(text(), "System Access Level *")]/following-sibling::select');
  const roleOptions = await roleSelect.locator('option').allInnerTexts();
  if (roleOptions.length > 1) await roleSelect.selectOption({ index: 1 });

  await page.getByRole('button', { name: /Next/i }).click();

  // STEP 3
  console.log('5. Compliance Data (Step 3) - Saving Draft...');
  await page.waitForSelector('text=Compliance Data');
  
  // Wait a moment for any backend validation to settle before saving
  await page.waitForTimeout(1000);
  await page.getByRole('button', { name: /Save as Draft/i }).click();

  console.log('6. Verifying result...');
  // Wait for either the success redirect to "Recent Onboardings" OR an error alert box.
  const result = await Promise.race([
    page.waitForSelector('text=Recent Onboardings', { timeout: 15000 }).then(() => 'success'),
    page.waitForSelector('.notification', { timeout: 15000 }).then(() => 'notification'), // Adjust selector if you have a specific toast class
    page.waitForSelector('text=Error', { timeout: 15000 }).then(() => 'error'),
    page.waitForSelector('text=Required', { timeout: 15000 }).then(() => 'error')
  ]).catch(() => 'timeout');

  if (result === 'success') {
    const pageText = await page.innerText('body');
    if (pageText.includes(firstName) && pageText.includes(lastName)) {
      console.log(`✅ Success! Found ${firstName} ${lastName} in the list.`);
    } else {
      console.log(`❌ Failed. Could not find ${firstName} ${lastName} in the list.`);
      await page.screenshot({ path: 'onboarding_missing_from_list.png' });
    }
  } else {
    console.log(`❌ Failed to save draft. Reason: ${result}`);
    await page.screenshot({ path: 'onboarding_failed.png' });
    const pageText = await page.innerText('body');
    console.log("Page Content Dump:", pageText.substring(0, 500));
  }
  
  expect(result).toBe('success');
});
