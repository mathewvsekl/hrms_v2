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

test('Functional Flow: Record Salary Advance', async ({ page }) => {
  test.setTimeout(60000);

  let alertMessage = '';
  page.on('dialog', async dialog => {
    alertMessage = dialog.message();
    await dialog.accept();
  });

  console.log('1. Navigating to Salary Advances page...');
  await page.goto('https://emm.anedins.com/salary-advances', { waitUntil: 'networkidle' });

  console.log('2. Clicking Record Advance...');
  const recordBtn = page.getByRole('button', { name: /Record Advance/i });
  await recordBtn.click();

  console.log('3. Filling out advance details...');
  await page.waitForSelector('text=Record Salary Advance');

  // Select first available employee
  const empSelect = page.locator('xpath=//label[text()="Employee"]/following-sibling::select');
  await empSelect.waitFor();
  const options = await empSelect.locator('option').allInnerTexts();
  if (options.length > 1) {
    await empSelect.selectOption({ index: 1 });
  } else {
    console.log('No employees available to select.');
  }

  // Fill amount
  const amountInput = page.locator('xpath=//label[text()="Amount"]/following-sibling::input');
  await amountInput.fill('500');

  // Fill installment
  const installmentInput = page.locator('xpath=//label[text()="Installment /mo"]/following-sibling::input');
  await installmentInput.fill('100');

  console.log('4. Recording Advance...');
  // There are two "Record Advance" buttons (one to open modal, one to submit).
  const submitBtn = page.locator('button[type="submit"]:has-text("Record Advance")');
  await submitBtn.click();

  console.log('5. Waiting for modal to close...');
  await page.waitForSelector('text=Record Salary Advance', { state: 'hidden', timeout: 15000 });
  
  console.log('✅ Salary Advance successfully recorded!');
});
