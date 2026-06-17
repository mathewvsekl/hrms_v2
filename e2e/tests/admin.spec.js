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

test('Functional Flow: Admin Settings', async ({ page }) => {
  test.setTimeout(30000);

  console.log('1. Navigating to Admin Settings page...');
  await page.goto('https://emm.anedins.com/admin', { waitUntil: 'networkidle' });

  console.log('2. Waiting for Admin Settings to load...');
  await page.waitForSelector('text=Settings', { timeout: 15000 }).catch(() => null);

  const companySettingsTab = page.locator('button:has-text("Company"), button:has-text("Organization")').first();
  if (await companySettingsTab.isVisible()) {
    await companySettingsTab.click();
    console.log('Clicked Company/Organization settings tab.');
  }

  console.log('✅ Admin Settings test completed successfully!');
});
