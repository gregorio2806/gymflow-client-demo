# GymFlow static client demo

This folder is a standalone presentation website. It does not use Laravel, PHP, a database, an API, or a login service.

The demo starts with fictional Iron House Fitness data. Adding members, checking people in, changing stock, and editing the public website are stored in the browser's `localStorage`. The **Reset demo data** button clears those changes and restores the sample data.

## Test locally

Open `index.html` directly in a browser, or run any simple local static server from this folder, for example:

```bash
python -m http.server 4173
```

Then open `http://localhost:4173`.

## Recommended deployment: separate GitHub demo repository

For a client presentation, keep the Laravel source code private and publish only this static website.

1. Create a new empty GitHub repository, such as `gymflow-client-demo`.
2. Copy the **contents of this `demo/` folder** (`index.html`, `styles.css`, `app.js`) into the root of that new repository. Do not copy the parent Laravel project.
3. From the new repository folder, commit and push the files:

    ```bash
    git init
    git add .
    git commit -m "Add GymFlow client demo"
    git branch -M main
    git remote add origin https://github.com/YOUR-USERNAME/gymflow-client-demo.git
    git push -u origin main
    ```

4. In GitHub, open the repository and go to **Settings → Pages**.
5. Under **Build and deployment**, choose **Deploy from a branch**.
6. Select branch **main**, folder **`/ (root)`**, then click **Save**.
7. After GitHub finishes publishing, open the URL shown on the Pages screen, usually:
   `https://YOUR-USERNAME.github.io/gymflow-client-demo/`

Every future push to that repository republishes the demo automatically. No server, database, cron job, or environment variables are needed.

## Alternative: deploy from this project repository

The repository contains `.github/workflows/deploy-demo.yml`. It publishes only this `demo/` folder, so the Laravel application is not required on the demo site.

1. Create a GitHub repository, such as `gymflow-demo`, and push this project to it.
2. From `C:\Users\Rhodwick\Documents\gym`, initialize and commit the project if it is not already a Git repository:

    ```bash
    git init
    git add demo .github/workflows/deploy-demo.yml
    git commit -m "Add static client demo"
    git branch -M main
    git remote add origin https://github.com/YOUR-USERNAME/gymflow-demo.git
    git push -u origin main
    ```

    If this project is already connected to GitHub, add and push the two paths instead:

    ```bash
    git add demo .github/workflows/deploy-demo.yml
    git commit -m "Add static client demo"
    git push
    ```

3. In GitHub, open the repository and go to **Settings → Pages**.
4. Under **Build and deployment**, choose **GitHub Actions** as the source.
5. Open the **Actions** tab and wait for **Deploy static demo to GitHub Pages** to finish.
6. Return to **Settings → Pages**. GitHub will show the public demo URL, usually:
   `https://YOUR-USERNAME.github.io/gymflow-demo/`

Every future push that changes `demo/` republishes the static site automatically.

## Important presentation note

This is a visual/product demo only. It must not be used for real member information, payments, passwords, or production operations. Browser storage is not secure storage and can be cleared by the visitor.
