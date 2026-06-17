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

test('Functional Flow: Reports', async ({ page }) => {
  test.setTimeout(30000);

  console.log('1. Navigating to Reports page...');
  await page.goto('https://emm.anedins.com/reports', { waitUntil: 'networkidle' });

  console.log('2. Waiting for Reports to load...');
  await page.waitForSelector('text=Reports', { timeout: 15000 });

  // Look for a report card or table to interact with
  const reportTab = page.locator('button:has-text("Attendance"), button:has-text("Payroll")').first();
  if (await reportTab.isVisible()) {
    await reportTab.click();
    console.log('Clicked a report tab.');
  }

  const downloadBtn = page.locator('button:has-text("Download"), button:has-text("Export")').first();
  if (await downloadBtn.isVisible()) {
    console.log('Download/Export button is available.');
  }

  console.log('✅ Reports test completed successfully!');
});
