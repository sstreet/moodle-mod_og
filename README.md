# OfficeGrader Project Documentation

# Description

The OfficeGrader Project Moodle plugin automates

1. Submitting student assignment documents to an OfficeGrader grading server, and
2. Distributing grades and marked-up graded documents to the students.

The plugin also automatically updates grades in the Moodle gradebook.

Without this plugin, when students submit documents for grading, the instructor must manually submit them to an OfficeGrader grading server, or if a grading server is not available, the instructor must run the appropriate OfficeGrader grading program. After the documents have been graded, the instructor must enter the grades into the Moodle gradebook and distribute the marked-up graded documents to the students.

The OfficeGrader Project plugin automates all this, thus removing the need for instructor intervention. The instructor only needs to create the OfficeGrader Projects in Moodle, and the plugin takes care of handling and grading student submissions.

# Requirements

- Moodle version 2.7 or greater
- A folder the web server has permission to read from and write to (moodledata by default)

# Quickstart Installation Instructions

1. Copy the &quot;og&quot; folder and its contents to the &quot;mod&quot; folder of your Moodle installation
2. Log in as admin and go to Administration &gt; Site administration &gt; Notifications
3. Verify the status of the OfficeGrader plugin is &quot;To be installed&quot;
4. Click &quot;Upgrade Moodle database now&quot; at the bottom of the page
5. Leave the Office Grader FTP path the moodledata folder (the default) [OR if you prefer, create a user and new ftp path OR use existing user and create ftp path. Add user to the www-data (or your web server&#39;s) group, and give write privileges to the folder to the web server.]
6. If not already set, edit Moodle cron schedule to execute every minute (or your preference, we recommend one minute.)

# Troubleshooting

Problem: The admin notifications page does not show the OfficeGrader plugin (step 3 of the &quot;Quick installation&quot; section, or step 8 of the &quot;Detailed installation instructions&quot; section).

Solution: Make sure you copied the &quot;og&quot; folder and all its contents to the &quot;mod&quot; folder of your Moodle installation.

Problem: The Ogin/ogout directory settings page says &quot;This value is not valid&quot; when you click &quot;Save changes&quot;.

Solution: Enter a valid path to an existing folder. If you don&#39;t know what to enter, just copy the default path (given beside the text input field).

Problem: The Ogin/ogout directory settings page says &quot;The specified directory is not readable or writable&quot; when you click &quot;Save changes&quot;.

Solution: Either change the permissions of the specified directory so the web server can read from and write to the folder, or choose a different folder. The default folder should work (the path is given beside the text input field).
