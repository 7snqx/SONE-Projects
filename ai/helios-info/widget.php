<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helios Widget</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: transparent; }
        
        .widget {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: white;
            border-radius: 16px;
            padding: 16px;
            max-width: 320px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        
        .widget-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .widget-header .logo {
            background: #e53935;
            padding: 6px;
            border-radius: 8px;
            display: flex;
        }
        
        .widget-header h3 {
            font-size: 14px;
            font-weight: 600;
        }
        
        .widget-header .date {
            margin-left: auto;
            font-size: 12px;
            opacity: 0.7;
        }
        
        .widget-stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 16px;
        }
        
        .widget-stat {
            text-align: center;
        }
        
        .widget-stat .value {
            font-size: 24px;
            font-weight: 700;
            color: #e53935;
        }
        
        .widget-stat .label {
            font-size: 10px;
            opacity: 0.7;
            text-transform: uppercase;
        }
        
        .widget-movies {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .widget-movie {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
        }
        
        .widget-movie .title {
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        
        .widget-movie .occ {
            font-size: 12px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
        }
        
        .widget-movie .occ.high { background: #c62828; }
        .widget-movie .occ.medium { background: #ff8f00; }
        .widget-movie .occ.low { background: #2e7d32; }
        
        .widget-footer {
            margin-top: 12px;
            text-align: center;
            font-size: 10px;
            opacity: 0.5;
        }
        
        .widget-footer a {
            color: inherit;
            text-decoration: none;
        }
        
        .material-symbols-rounded {
            font-family: 'Material Symbols Rounded';
            font-size: 18px;
        }
        
        .spinner {
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255,255,255,0.2);
            border-top-color: #e53935;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="widget" id="widget">
        <div class="widget-header">
            <div class="logo">
                <span class="material-symbols-rounded">movie</span>
            </div>
            <h3>Helios Łódź</h3>
            <span class="date" id="widgetDate">-</span>
        </div>
        <div class="widget-stats">
            <div class="widget-stat">
                <div class="value" id="widgetOcc">-</div>
                <div class="label">Obłożenie</div>
            </div>
            <div class="widget-stat">
                <div class="value" id="widgetMovies">-</div>
                <div class="label">Filmów</div>
            </div>
            <div class="widget-stat">
                <div class="value" id="widgetScreenings">-</div>
                <div class="label">Seansów</div>
            </div>
        </div>
        <div class="widget-movies" id="widgetMoviesList">
            <div class="spinner"></div>
        </div>
        <div class="widget-footer">
            <a href="https://helios.pl" target="_blank">Powered by Helios</a>
        </div>
    </div>
    
    <script>
        async function loadWidget() {
            try {
                const response = await fetch('api.php?date=' + new Date().toISOString().slice(0, 10));
                const data = await response.json();
                
                // Date
                document.getElementById('widgetDate').textContent = 
                    new Date().toLocaleDateString('pl-PL', { day: 'numeric', month: 'short' });
                
                // Stats
                const totals = data.totals || {};
                document.getElementById('widgetOcc').textContent = (totals.percent || 0) + '%';
                document.getElementById('widgetMovies').textContent = (data.movies || []).length;
                document.getElementById('widgetScreenings').textContent = totals.screenings || 0;
                
                // Top 3 movies by occupancy
                const movies = data.movies || [];
                const sortedMovies = movies
                    .map(m => ({
                        title: m.movieTitle,
                        occupancy: m.screenings?.reduce((acc, s) => {
                            const occ = s.stats?.occupied || 0;
                            const tot = s.stats?.total || 1;
                            return acc + (occ / tot);
                        }, 0) / (m.screenings?.length || 1) * 100 || 0
                    }))
                    .sort((a, b) => b.occupancy - a.occupancy)
                    .slice(0, 3);
                
                const moviesList = document.getElementById('widgetMoviesList');
                moviesList.innerHTML = sortedMovies.map(m => {
                    const occClass = m.occupancy >= 50 ? 'high' : m.occupancy >= 25 ? 'medium' : 'low';
                    return `
                        <div class="widget-movie">
                            <span class="title">${escapeHtml(m.title)}</span>
                            <span class="occ ${occClass}">${Math.round(m.occupancy)}%</span>
                        </div>
                    `;
                }).join('') || '<div style="text-align:center;opacity:0.5;">Brak danych</div>';
                
            } catch (e) {
                console.error('Widget error:', e);
                document.getElementById('widgetMoviesList').innerHTML = 
                    '<div style="text-align:center;opacity:0.5;">Błąd ładowania</div>';
            }
        }
        
        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
        
        loadWidget();
        // Refresh every 5 minutes
        setInterval(loadWidget, 5 * 60 * 1000);
    </script>
</body>
</html>
