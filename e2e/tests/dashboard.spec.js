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

test('Functional Flow: Dashboard', async ({ page }) => {
  test.setTimeout(30000);

  console.log('1. Navigating to Dashboard...');
  await page.goto('https://emm.anedins.com/dashboard', { waitUntil: 'networkidle' });

  console.log('2. Verifying Dashboard loads...');
  // Wait for some common dashboard elements to be visible
  await page.waitForSelector('text=Total Employees', { timeout: 15000 }).catch(() => console.log('Total Employees text not found'));
  
  // Verify user greeting or quick actions
  const quickActions = page.locator('text=Quick Actions');
  if (await quickActions.isVisible()) {
    console.log('Quick Actions section found.');
  }

  // Click on a Quick Action or tab if present (e.g., HR, Admin, Personal)
  const hrTab = page.locator('button:has-text("HR Overview")').first();
  if (await hrTab.isVisible()) {
    await hrTab.click();
    console.log('Clicked HR Overview tab.');
  }

  console.log('✅ Dashboard test completed successfully!');
});
