# DATS Setup and Database Recovery

## Database Configuration

DATS uses MySQL/MariaDB through PHP PDO (`pdo_mysql`). Configure the application connection using these environment variables on the web server:

- `DATS_DB_HOST` (defaults to `127.0.0.1`)
- `DATS_DB_NAME` (defaults to `dats`)
- `DATS_DB_USER` (defaults to `root`)
- `DATS_DB_PASSWORD` (defaults to an empty password)

Set the values in the web server or hosting environment, not in this README or a shared backup folder. Obtain the approved database host, database name, username, and password from the system/database administrator or hosting provider. Use a dedicated database account with the privileges the application requires. Never put production credentials, API keys, or passwords in source control or shared files.

## Create and Store a Backup

1. Sign in to DATS with an Admin account and open **Setup → Database**.
2. Select **Backup Database**. The browser downloads a file named `DATS_Database_Backup_YYYY-MM-DD.sql` (for example, `DATS_Database_Backup_2026-10-02.sql`).
3. The file is generated for the download and is not kept on the application server. Save it in a secure local backup location.
4. For off-server storage, sign in to the organization's approved Google account, open the restricted Google Drive backup folder, upload the `.sql` file, and confirm it finished uploading. Google Drive integration is not built into DATS; the upload is manual.
5. Keep access to the backup folder restricted to authorized personnel. Database backups contain personal and account data.

Backups made here cover database tables, their data, and database views. They do **not** include uploaded/archive files or application source files. Back up the `archive/`, `uploads/`, and other institution-specific file-storage folders separately using the organization's approved secure storage process.

## Restore a Database

Restoring replaces the target database's current tables and data. It is destructive and cannot be undone. Verify the selected backup and target database before continuing, and preserve a separate copy of the current database when possible.

1. Reinstall/copy the DATS application files and separately recover its file-storage folders if those files are needed.
2. Create an empty MySQL/MariaDB database and configure the server environment variables above with the approved connection values. Ensure PHP has PDO MySQL enabled and the database user has the privileges needed to create/drop tables and views and insert data.
3. Open the DATS sign-in page, connect the application to that database, and sign in with an active Admin account. If no Admin account exists in the restored database, restore using the hosting provider's database administration tool instead, then sign in with an account from the backup.
4. Open **Setup → Database**, select the supported `.sql` backup, and choose **Restore Database**. Review and accept the confirmation warning. DATS accepts its own marked backup format; arbitrary SQL files are rejected.
5. Wait for the success or error result. If a restore reports that it stopped partway through, do not use the application until the database has been repaired or restored again.
6. Confirm the application can sign in and review important student, document, and setup records. Confirm the separately backed-up archive files are present as well.

The `.sql` file can also be imported into the configured empty database using the hosting provider's trusted MySQL/MariaDB administration tool. Do not import it into a production database unless replacing that database is intended.

## Recovery Credentials

The system requires the database host, database name, database username, and database password configured through `DATS_DB_HOST`, `DATS_DB_NAME`, `DATS_DB_USER`, and `DATS_DB_PASSWORD`. Obtain current values from the hosting/database administrator or secure deployment configuration. The restored database includes DATS user accounts and password hashes; users sign in with their existing account credentials. If credentials or environment configuration are unavailable, contact the authorized administrator/provider rather than placing secrets in this document or a Drive folder.