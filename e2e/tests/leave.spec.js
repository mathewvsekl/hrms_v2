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
            },
            {
              name: 'adminViewMode',
              value: 'employee'
            }
          ]
        }
      ]
    });
  }
});

test('Functional Flow: Apply for Leave', async ({ page }) => {
  test.setTimeout(60000);

  console.log('1. Navigating to Leave page...');
  await page.goto('https://emm.anedins.com/leave', { waitUntil: 'networkidle' });

  console.log('2. Clicking Apply Leave...');
  await page.getByRole('button', { name: /Apply Leave/i }).click();

  console.log('3. Filling out the request...');
  await page.waitForSelector('text=Apply for Leave');

  // Select Category
  const categorySelect = page.locator('xpath=//label[text()="Category"]/following-sibling::select');
  // Make sure options exist before selecting
  await categorySelect.waitFor();
  const options = await categorySelect.locator('option').allInnerTexts();
  if (options.length > 1) {
    await categorySelect.selectOption({ index: 1 });
  }

  // From Date
  const fromDateInput = page.locator('xpath=//label[text()="From"]/following-sibling::div//input').first();
  await fromDateInput.fill('15062026'); // DDMMYYYY format for custom DateInput

  // To Date
  const toDateInput = page.locator('xpath=//label[text()="To"]/following-sibling::div//input').first();
  await toDateInput.fill('17062026'); // DDMMYYYY format

  // Remarks
  const remarksTextarea = page.locator('textarea');
  await remarksTextarea.fill('Automated functional test for leave request');

  console.log('4. Continuing to Preview...');
  await page.getByRole('button', { name: /Continue to Preview/i }).click();

  console.log('5. Waiting for Preview and saving draft...');
  await page.waitForSelector('text=Request Summary');
  
  await page.getByRole('button', { name: /Save as Draft/i }).click();

  console.log('6. Verifying Success Notification...');
  // Check for success notification
  const successMessage = await page.waitForSelector('text=Success', { timeout: 15000 });
  expect(successMessage).toBeTruthy();
  
  console.log('✅ Leave request successfully drafted!');
});
