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

test('Functional Flow: Run Payroll', async ({ page }) => {
  test.setTimeout(60000);

  // Setup dialog handler for the expected success alert
  let alertMessage = '';
  page.on('dialog', async dialog => {
    alertMessage = dialog.message();
    await dialog.accept();
  });

  console.log('1. Navigating to Payroll page...');
  await page.goto('https://emm.anedins.com/payroll', { waitUntil: 'networkidle' });

  // Add a slight delay for records to load
  await page.waitForTimeout(2000);

  console.log('2. Clicking Run Payroll...');
  const runBtn = page.getByRole('button', { name: /Run Payroll/i }).first();
  await runBtn.click();

  console.log('3. Waiting for Payroll Preview modal...');
  await page.waitForSelector('text=Payroll Preview', { state: 'visible', timeout: 15000 });

  console.log('4. Handling preview modal...');
  const confirmBtn = page.getByRole('button', { name: /Confirm & Generate/i });
  const isEnabled = await confirmBtn.isEnabled();

  if (isEnabled) {
    console.log('Records found. Clicking Confirm & Generate...');
    await confirmBtn.click();
    await page.waitForSelector('text=Payroll Preview', { state: 'hidden', timeout: 15000 });
    console.log('✅ Payroll successfully generated!');
  } else {
    console.log('No records found for this period. Clicking Cancel...');
    const cancelBtn = page.getByRole('button', { name: /Cancel/i });
    await cancelBtn.click();
    await page.waitForSelector('text=Payroll Preview', { state: 'hidden', timeout: 15000 });
    console.log('✅ Payroll preview handled (no records).');
  }
});
