</main>

<footer class="site-footer">

    <div class="footer-container">

        <div class="footer-brand">
            <a href="<?= BASE_URL ?>/index.php">
                Cine<span>Stream</span>
            </a>

            <p>
                Your home for movies, series and entertainment.
            </p>
        </div>


        <div class="footer-links">

            <div class="footer-column">

                <h3>Explore</h3>

                <a href="<?= BASE_URL ?>/index.php">
                    Home
                </a>

                <a href="<?= BASE_URL ?>/movies.php">
                    Movies
                </a>

                <a href="<?= BASE_URL ?>/search.php">
                    Search
                </a>

            </div>


            <div class="footer-column">

                <h3>Account</h3>

                <?php if (isLoggedIn()): ?>

                    <a href="<?= BASE_URL ?>/profile.php">
                        Profile
                    </a>

                    <a href="<?= BASE_URL ?>/watch.php">
                        Continue Watching
                    </a>

                <?php else: ?>

                    <a href="<?= BASE_URL ?>/login.php">
                        Sign In
                    </a>

                    <a href="<?= BASE_URL ?>/register.php">
                        Register
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <div class="footer-bottom">

        <p>
            &copy; <?= date('Y') ?> CineStream.
            All rights reserved.
        </p>

        <p>
            Demo streaming platform
        </p>

    </div>

</footer>


<script
    src="<?= BASE_URL ?>/assets/js/app.js"
></script>

</body>
</html>