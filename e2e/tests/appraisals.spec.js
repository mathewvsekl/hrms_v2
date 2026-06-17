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

test('Functional Flow: Initiate Appraisal Cycle', async ({ page }) => {
  test.setTimeout(60000);

  // Setup dialog handler for the expected success alert
  let alertMessage = '';
  page.on('dialog', async dialog => {
    alertMessage = dialog.message();
    await dialog.accept();
  });

  console.log('1. Navigating to Appraisals page...');
  await page.goto('https://emm.anedins.com/appraisals', { waitUntil: 'networkidle' });

  console.log('2. Clicking Initiate New Cycle...');
  const initiateBtn = page.getByRole('button', { name: /Initiate New Cycle/i });
  await initiateBtn.click();

  console.log('3. Filling out cycle details...');
  await page.waitForSelector('text=Initiate Appraisal Cycle');

  // Fill Employee Deadline
  const empDeadlineLabel = page.locator('xpath=//label[text()="Employee Deadline"]/following-sibling::div//input').first();
  await empDeadlineLabel.fill('20122026');

  // Fill Manager Deadline
  const mgrDeadlineLabel = page.locator('xpath=//label[text()="Manager Deadline"]/following-sibling::div//input').first();
  await mgrDeadlineLabel.fill('24122026');

  // Fill HR Deadline
  const hrDeadlineLabel = page.locator('xpath=//label[text()="HR Finalization Deadline"]/following-sibling::div//input').first();
  await hrDeadlineLabel.fill('28122026');

  // Check the first available office checkbox
  const checkbox = page.locator('input[type="checkbox"]').first();
  await checkbox.check();

  console.log('4. Initiating Cycle...');
  const submitBtn = page.getByRole('button', { name: 'Initiate Cycle' });
  await submitBtn.click();

  console.log('5. Waiting for success alert...');
  // The dialog handler will catch the alert. We can wait for the modal to disappear.
  await page.waitForSelector('text=Initiate Appraisal Cycle', { state: 'hidden', timeout: 10000 });
  
  expect(alertMessage).toBeTruthy();
  console.log('Alert received:', alertMessage);

  console.log('✅ Appraisal cycle successfully initiated!');
});
