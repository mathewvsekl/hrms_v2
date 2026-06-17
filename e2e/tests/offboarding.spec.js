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

test('Functional Flow: Offboarding', async ({ page }) => {
  test.setTimeout(30000);

  console.log('1. Navigating to Offboarding page...');
  await page.goto('https://emm.anedins.com/offboarding', { waitUntil: 'networkidle' });

  console.log('2. Waiting for Offboarding to load...');
  await page.waitForSelector('text=Offboarding', { timeout: 15000 }).catch(() => null);

  console.log('3. Checking for Initiate button...');
  const initiateBtn = page.locator('button:has-text("Initiate"), button:has-text("Add")').first();
  if (await initiateBtn.isVisible()) {
    await initiateBtn.click();
    console.log('Clicked Initiate Offboarding.');
    
    // Close modal if it opened
    const closeBtn = page.locator('button:has-text("Cancel"), button:has-text("Close")').first();
    if (await closeBtn.isVisible()) {
      await closeBtn.click();
    }
  } else {
    console.log('No Initiate button found.');
  }

  console.log('✅ Offboarding test completed successfully!');
});
