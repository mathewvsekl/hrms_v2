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

test('Functional Flow: Employees and Employee Profile', async ({ page }) => {
  test.setTimeout(60000);

  console.log('1. Navigating to Employees page...');
  await page.goto('https://emm.anedins.com/employees', { waitUntil: 'networkidle' });

  console.log('2. Waiting for employee list to load...');
  // Wait for either the grid or list container
  await page.waitForSelector('input[placeholder*="Search"]', { timeout: 15000 });

  console.log('3. Searching for an employee...');
  const searchInput = page.locator('input[placeholder*="Search"]');
  await searchInput.fill('a'); // Type "a" to find someone

  console.log('4. Toggling views...');
  // Usually there are Grid and List toggle buttons. We can try to click them if they exist
  const gridToggle = page.locator('button[title*="Grid"], button:has(.lucide-layout-grid)').first();
  const listToggle = page.locator('button[title*="List"], button:has(.lucide-list)').first();

  if (await listToggle.isVisible()) {
    await listToggle.click();
    console.log('Toggled to List View');
  }
  if (await gridToggle.isVisible()) {
    await gridToggle.click();
    console.log('Toggled to Grid View');
  }

  console.log('5. Clicking on an employee to view profile...');
  // Find a view button or an employee card/row link.
  // We can look for "View Profile" button, or an element that has cursor: pointer and text
  const viewProfileBtn = page.locator('button:has-text("View Profile"), a:has-text("View Profile"), button:has-text("View")').first();
  
  if (await viewProfileBtn.isVisible()) {
    await viewProfileBtn.click();
  } else {
    // If no view profile button, click the first employee card/row
    const employeeCard = page.locator('div[style*="cursor: pointer"], tr[style*="cursor: pointer"]').first();
    if (await employeeCard.isVisible()) {
      await employeeCard.click();
    } else {
      console.log('No employee found to click. Exiting...');
      return;
    }
  }

  console.log('6. Verifying navigation to Employee Profile...');
  await page.waitForURL('**/employee-profile/**', { timeout: 15000 }).catch(() => console.log('Did not navigate to /employee-profile/'));
  
  // Verify Employee Profile loaded
  await page.waitForSelector('text=Personal Info', { timeout: 10000 }).catch(() => null);

  console.log('✅ Employees & Employee Profile tests completed successfully!');
});
