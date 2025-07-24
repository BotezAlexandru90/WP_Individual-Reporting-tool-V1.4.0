# EVE Corp Skill Monitor

A WordPress plugin for EVE Online corporations to monitor character skill levels and training times against corporation-defined standards. This tool provides an easy-to-use interface within the WordPress admin dashboard for corp leadership to quickly assess which skills a member's characters need to train.

---

## Table of Contents

- [Features](#features)
- [How It Works](#how-it-works)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [CSV File Formats](#csv-file-formats)
- [Author](#author)
- [License](#license)

## Features

-   **Main/Alt Character Structure:** Properly handles the EVE Online character structure, grouping Alts under their Main.
-   **Dynamic Dropdown Selection:** Select a Main character, which then populates a second dropdown with only the Alts belonging to that Main.
-   **Customizable Skill Sections:** Group skills into logical sections like "Tanking," "Guns," "Missiles," etc.
-   **Reference Level Comparison:** Each skill section has a "Reference Level." The plugin only shows skills that are at or below this level.
-   **Training Time Calculation:** Automatically calculates the time required (in days) for a skill to train from its current level to the section's reference level.
-   **Flexible Settings Panel:** A dedicated settings page allows you to:
    -   Set the paths to your data files.
    -   Create, edit, or delete any skill section.
    -   Assign or re-assign skills to sections.
    -   Change the reference level for any section.
-   **Compact Grid View:** Displays the results in a clean, responsive grid, making it easy to see the character's status at a glance.
-   **Secure Access:** The plugin pages are only accessible to users with `Administrator` or `Editor` roles.

## How It Works

The plugin's logic is designed to be fast and efficient, offloading complex spreadsheet functions to a dedicated server-side process.

1.  **Data Source:** The plugin reads two separate CSV files that you provide and host.
    -   `skills_data.csv`: A comprehensive list of every skill for every character in the corporation.
    -   `skill_multipliers.csv`: A reference file containing the training multiplier for each skill in EVE.
2.  **SP Calculation:** It uses a hardcoded map of the Skill Points (SP) required for each skill level (1-5).
3.  **The Formula:** When you select a character, the plugin calculates the time to train for each relevant skill using the following logic:
    -   `Target SP = (SP for Reference Level) * (Skill Multiplier)`
    -   `Current SP = (SP for Current Level) * (Skill Multiplier)`
    -   `SP Needed = Target SP - Current SP`
    -   `Days to Train = SP Needed / (SP per day)`
4.  **Display:** The results are only displayed for skills that have not yet met or exceeded the reference level, keeping the output focused on what needs training.

This specialized approach is significantly faster than using a general-purpose tool like Google Sheets, as it performs only the exact calculations needed, directly on the server, without the overhead of a large web application.

## Requirements

-   WordPress 5.0 or later.
-   User account with **Administrator** or **Editor** role.
-   Two CSV files (see [CSV File Formats](#csv-file-formats)) hosted on your server or at a publicly accessible URL.

## Installation

1.  Download the `eve-corp-skill-monitor.php` file.
2.  In your WordPress admin dashboard, navigate to **Plugins** > **Add New**.
3.  Click **Upload Plugin**, choose the `.zip` file of the plugin, and click **Install Now**. (Alternatively, upload the `.php` file directly to your `/wp-content/plugins/` directory).
4.  Once installed, click **Activate**.

## Configuration

Before the plugin will work, you must configure it.

1.  Navigate to **EVE Monitor** > **Settings** in the WordPress admin menu.
2.  **File Paths**:
    -   Fill in the **Skills Data CSV Path or URL** and **Skill Multipliers CSV Path or URL** fields.
    -   **Important:** Using a local server path is highly recommended for performance and security (e.g., `/var/www/html/wp-content/uploads/eve-data/skills_data.csv`). A URL will also work if the file is publicly accessible.
3.  **Skill Sections Management**:
    -   The plugin comes pre-populated with default sections. You can edit any section's name, reference level, or the list of skills within it.
    -   To delete a section, check the "Mark for deletion" box within that section's card.
    -   To add a new section, fill out the fields under "Add New Section".
4.  Click **Save All Settings**.

## Usage

1.  Navigate to the main **EVE Monitor** page from your WordPress admin dashboard.
2.  Select a **Main Character** from the first dropdown.
3.  The **Alt Character** dropdown will automatically populate. Select the character you wish to inspect.
4.  The skill analysis will instantly appear below in a compact grid, showing only the skill sections where training is required.

## CSV File Formats

The plugin relies on a specific format for its CSV files. The delimiter must be a **semicolon (`;`)**.

### `skills_data.csv`

This file contains the skill levels for all characters. The matching is case-insensitive.

-   **Format:** `MainName;AltName;Skill Name;Level`
-   **Example:**
    ```csv
    Surama Badasaz;Surama Badasaz;Amarr Cruiser;5
    Surama Badasaz;Herad Badasaz;Medium Energy Turret;4
    AnotherMain;TheirAlt;Gallente Battleship;3
    ```

### `skill_multipliers.csv`

This file contains the training time multiplier for each skill.

-   **Format:** `Skill Name;Multiplier`
-   **Example:**
    ```csv
    Armor Layering;3
    Amarr Cruiser;5
    Thermodynamics;2
    ```
<img width="1744" height="859" alt="image" src="https://github.com/user-attachments/assets/63f013c8-b2d4-4d94-b1f9-e8166444867c" />
<img width="768" height="842" alt="image" src="https://github.com/user-attachments/assets/398d4c79-20fe-4bb3-9802-08dc2caf2381" />
<img width="576" height="297" alt="image" src="https://github.com/user-attachments/assets/0e1d9558-0028-47c3-a4b4-560f7558609d" />

## Author

-   **Surama Badasaz**

## License

-   **GPL-2.0+**
