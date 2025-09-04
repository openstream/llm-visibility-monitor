const { chromium } = require('playwright');

async function debugDropdown() {
    const browser = await chromium.launch({ headless: false });
    const page = await browser.newPage();
    
    // Navigate to the WordPress admin login page
    await page.goto('http://localhost/wp-admin/');
    
    // You'll need to provide the actual login credentials
    console.log('Please log in manually, then navigate to the prompts page...');
    console.log('URL should be: http://localhost/wp-admin/tools.php?page=llmvm-prompts');
    
    // Wait for user to navigate to the prompts page
    await page.waitForURL('**/tools.php?page=llmvm-prompts');
    
    console.log('On prompts page, let me inspect the dropdown...');
    
    // Take a screenshot
    await page.screenshot({ path: 'prompts-page.png' });
    
    // Check if the input field exists
    const inputField = await page.locator('#llmvm-new-prompt-models-search');
    console.log('Input field found:', await inputField.count() > 0);
    
    // Click on the input field
    await inputField.click();
    
    // Wait a bit for any animations
    await page.waitForTimeout(1000);
    
    // Take another screenshot after clicking
    await page.screenshot({ path: 'after-click.png' });
    
    // Check for the dropdown
    const dropdown = await page.locator('.ui-autocomplete');
    console.log('Dropdown found:', await dropdown.count() > 0);
    console.log('Dropdown visible:', await dropdown.isVisible());
    
    // Check dropdown content
    const dropdownItems = await page.locator('.ui-autocomplete li');
    console.log('Dropdown items count:', await dropdownItems.count());
    
    // Get console logs
    page.on('console', msg => console.log('PAGE LOG:', msg.text()));
    
    // Keep browser open for manual inspection
    console.log('Browser will stay open for manual inspection. Press Ctrl+C to close.');
    
    // Wait indefinitely (user can close manually)
    await new Promise(() => {});
}

debugDropdown().catch(console.error);
