const { test, expect } = require('@playwright/test');

test('navigate to emm.anedins.com and check title', async ({ page }) => {
  console.log('Navigating to https://emm.anedins.com/');
  const response = await page.goto('https://emm.anedins.com/');
  
  console.log('Status code:', response.status());
  
  const title = await page.title();
  console.log('Page title is:', title);
  
  expect(title).not.toBeNull();
  
  // optionally take a screenshot
  await page.screenshot({ path: 'screenshot.png' });
  console.log('Screenshot saved to screenshot.png');
});
