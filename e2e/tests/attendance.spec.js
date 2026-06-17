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

test('Functional Flow: Mark Attendance', async ({ page }) => {
  test.setTimeout(60000);

  console.log('1. Navigating to Attendance page...');
  await page.goto('https://emm.anedins.com/attendance', { waitUntil: 'networkidle' });

  console.log('2. Waiting for attendance grid to load...');
  // Wait for the table body to contain at least one row with a select
  await page.waitForSelector('table.data-table tbody tr select', { timeout: 15000 });

  console.log('3. Searching for a specific employee...');
  const searchInput = page.getByPlaceholder('Search by name or Staff ID...');
  await searchInput.fill('Mathew');
  
  // Wait a little for the filter to apply
  await page.waitForTimeout(1000);

  const row = page.locator('table.data-table tbody tr').first();
  await expect(row).toBeVisible();

  console.log('4. Modifying attendance status...');
  const selectDropdown = row.locator('select');
  
  // Select the second option available (e.g. "Present" or "Absent")
  // We use index 2 because index 0 is "Select Status" and 1 might be the first real status
  await selectDropdown.selectOption({ index: 2 });

  console.log('5. Saving the record...');
  // After modifying, the "Save this record" button should appear or become enabled
  // It has a title="Save this record"
  const saveBtn = row.locator('button[title="Save this record"]');
  await saveBtn.click();

  console.log('6. Verifying Success Notification...');
  // Wait for the SweetAlert or notification saying "Success" or "Record saved"
  await expect(page.locator('text=Success').first()).toBeVisible({ timeout: 10000 });
  
  console.log('✅ Attendance successfully marked and saved!');
});
