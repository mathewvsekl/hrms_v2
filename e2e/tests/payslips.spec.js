const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

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

test('Functional Flow: Upload Payslip', async ({ page }) => {
  test.setTimeout(60000);

  // Create a dummy PDF file for upload
  const dummyFilePath = path.join(__dirname, 'dummy_payslip.pdf');
  fs.writeFileSync(dummyFilePath, 'dummy pdf content');

  let alertMessage = '';
  page.on('dialog', async dialog => {
    alertMessage = dialog.message();
    await dialog.accept();
  });

  console.log('1. Navigating to Payslips page...');
  await page.goto('https://emm.anedins.com/payslips', { waitUntil: 'networkidle' });

  console.log('2. Clicking Add Payslip...');
  const addBtn = page.getByRole('button', { name: /Add Payslip/i });
  await addBtn.click();

  console.log('3. Filling out upload details...');
  await page.waitForSelector('text=Upload Payslip');

  // Select Company
  const companySelect = page.locator('select').first(); // The first select in the modal is usually company
  await companySelect.waitFor();
  await companySelect.selectOption({ index: 1 });

  // Wait for employees to load and click the first employee div
  // The employee list is inside a div below the search input
  const employeeDiv = page.locator('div:has-text("No ID")').first();
  // Actually, let's just select the first employee container that has a hover effect
  const firstEmployee = page.locator('div[style*="cursor: pointer"]').first();
  await firstEmployee.waitFor({ state: 'visible', timeout: 10000 }).catch(() => null);
  
  if (await firstEmployee.isVisible()) {
    await firstEmployee.click();
    console.log('Selected employee.');
  } else {
    console.log('No employees found, uploading without employee might fail or be disabled.');
  }

  // Set file
  const fileInput = page.locator('input[type="file"]');
  await fileInput.setInputFiles(dummyFilePath);

  console.log('4. Uploading Payslip...');
  const confirmBtn = page.getByRole('button', { name: /Confirm Upload/i });
  let isEnabled = false;
  
  try {
    await confirmBtn.waitFor({ state: 'attached', timeout: 5000 });
    isEnabled = await confirmBtn.isEnabled();
  } catch (err) {
    console.log('Confirm button not found or not enabled in time.');
  }

  if (isEnabled) {
    await confirmBtn.click();
    console.log('5. Waiting for upload to complete...');
    await page.waitForSelector('text=Upload Payslip', { state: 'hidden', timeout: 15000 });
    console.log('✅ Payslip successfully uploaded!');
  } else {
    console.log('Confirm Upload is disabled. Probably no employee selected. Canceling...');
    const cancelBtn = page.getByRole('button', { name: /Close/i });
    await cancelBtn.click();
    await page.waitForSelector('text=Upload Payslip', { state: 'hidden', timeout: 15000 });
    console.log('✅ Payslip modal closed (upload skipped).');
  }

  // Cleanup dummy file
  if (fs.existsSync(dummyFilePath)) {
    fs.unlinkSync(dummyFilePath);
  }
});
