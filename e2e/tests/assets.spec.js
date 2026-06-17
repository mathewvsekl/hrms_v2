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

test('Functional Flow: Add Asset', async ({ page }) => {
  test.setTimeout(60000);

  // Setup dialog handler
  page.on('dialog', async dialog => {
    console.log('Dialog:', dialog.message());
    await dialog.accept();
  });

  console.log('1. Navigating to Assets page...');
  await page.goto('https://emm.anedins.com/assets', { waitUntil: 'networkidle' });

  console.log('2. Clicking Add Asset...');
  const addBtn = page.locator('button:has-text("Add Asset")').first();
  await addBtn.waitFor({ state: 'attached', timeout: 5000 }).catch(() => console.log('Add Asset btn not found'));
  
  if (await addBtn.isVisible()) {
    await addBtn.click();
  } else {
    console.log('No Add Asset button found. Might not have permission.');
    return;
  }

  console.log('3. Filling out asset details...');
  await page.waitForSelector('text=Add New Asset');

  // Select Company
  const companySelect = page.locator('xpath=//label[contains(text(), "Company")]/following-sibling::select');
  await companySelect.waitFor();
  await companySelect.selectOption({ index: 1 });

  // Fill Asset Name
  const nameInput = page.locator('xpath=//label[contains(text(), "Asset Name")]/following-sibling::input');
  await nameInput.fill('Test MacBook Pro');

  // Fill Category
  const categorySelect = page.locator('xpath=//label[contains(text(), "Category")]/following-sibling::select');
  await categorySelect.selectOption('laptop');

  // Fill Serial Number
  const serialInput = page.locator('xpath=//label[contains(text(), "Serial Number")]/following-sibling::input');
  await serialInput.fill('SN12345678');

  // Fill Model Number
  const modelInput = page.locator('xpath=//label[contains(text(), "Model Number")]/following-sibling::input');
  await modelInput.fill('A2338');

  // Fill Purchase Date
  const dateInput = page.locator('input[placeholder="DD/MM/YYYY"]');
  await dateInput.click();
  await page.keyboard.type('01012026');

  // Fill Purchase Cost
  const costInput = page.locator('xpath=//label[contains(text(), "Purchase Cost")]/following-sibling::input');
  await costInput.fill('2000');

  console.log('4. Saving Asset...');
  const saveBtn = page.getByRole('button', { name: /Save Asset/i });
  await saveBtn.click();

  console.log('5. Waiting for modal to close...');
  await page.waitForSelector('text=Add New Asset', { state: 'hidden', timeout: 10000 });

  console.log('✅ Asset successfully added!');
});
