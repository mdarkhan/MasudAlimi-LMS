// Result share card: draws a 1080×1350 (4:5 — the shape WhatsApp status and Facebook show uncropped)
// image of a released result on a <canvas>, entirely in the browser. Nothing is uploaded; the student
// decides whether to share or save it. Loaded on demand (window.loadShareCard) so pages without a
// result never pay for it.

const W = 1080;
const H = 1350;
const C = {
    deep: '#0b3a2c',
    malachite: '#0f6b4f',
    gold: '#b98b2e',
    goldLight: '#e3c27a',
    white: '#ffffff',
};

// An eight-pointed star (khatam): two squares, one turned 45°.
function starPath(ctx, cx, cy, r) {
    ctx.beginPath();
    for (let i = 0; i < 16; i++) {
        const a = (Math.PI / 8) * i - Math.PI / 2;
        const rr = i % 2 === 0 ? r : r * 0.78;
        const x = cx + rr * Math.cos(a);
        const y = cy + rr * Math.sin(a);
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    }
    ctx.closePath();
}

function wrap(ctx, text, maxWidth) {
    const words = String(text).split(/\s+/);
    const lines = [];
    let line = '';
    for (const w of words) {
        const test = line ? line + ' ' + w : w;
        if (ctx.measureText(test).width > maxWidth && line) {
            lines.push(line);
            line = w;
        } else {
            line = test;
        }
    }
    if (line) lines.push(line);
    return lines.slice(0, 3);
}

export default async function renderShareCard(d) {
    await document.fonts.ready;
    const canvas = document.createElement('canvas');
    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext('2d');
    const font = (size, weight = 'bold') => `${weight} ${size}px kalpurush, sans-serif`;

    // ground
    const g = ctx.createLinearGradient(0, 0, W, H);
    g.addColorStop(0, C.deep);
    g.addColorStop(1, C.malachite);
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, W, H);

    // faint star lattice
    ctx.strokeStyle = 'rgba(255,255,255,0.06)';
    ctx.lineWidth = 2;
    for (let y = 0; y <= H + 120; y += 120) {
        for (let x = (y / 120) % 2 ? 60 : 0; x <= W + 120; x += 120) {
            starPath(ctx, x, y, 34);
            ctx.stroke();
        }
    }

    // gold frame
    ctx.strokeStyle = C.gold;
    ctx.lineWidth = 4;
    ctx.beginPath();
    ctx.roundRect(44, 44, W - 88, H - 88, 36);
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';
    ctx.fillStyle = C.white;

    // brand
    ctx.font = font(46);
    ctx.fillText(d.brand, W / 2, 150);
    ctx.fillStyle = C.goldLight;
    ctx.fillRect(W / 2 - 60, 178, 120, 4);

    // exam
    ctx.fillStyle = C.white;
    ctx.font = font(58);
    const titleLines = wrap(ctx, d.title, 880);
    titleLines.forEach((l, i) => ctx.fillText(l, W / 2, 275 + i * 70));
    let y = 275 + titleLines.length * 70;
    if (d.course) {
        ctx.font = font(34, 'normal');
        ctx.globalAlpha = 0.85;
        ctx.fillText(d.course, W / 2, y + 4);
        ctx.globalAlpha = 1;
    }

    // medallion with the score
    const cy = 670;
    starPath(ctx, W / 2, cy, 270);
    ctx.fillStyle = 'rgba(255,255,255,0.08)';
    ctx.fill();
    ctx.strokeStyle = C.gold;
    ctx.lineWidth = 5;
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(W / 2, cy, 190, 0, Math.PI * 2);
    ctx.strokeStyle = 'rgba(227,194,122,0.55)';
    ctx.lineWidth = 2;
    ctx.stroke();

    ctx.fillStyle = C.white;
    ctx.font = font(34, 'normal');
    ctx.globalAlpha = 0.85;
    ctx.fillText(d.scoreLabel, W / 2, cy - 95);
    ctx.globalAlpha = 1;
    ctx.font = font(170);
    ctx.fillText(d.score, W / 2, cy + 60);
    ctx.font = font(56, 'normal');
    ctx.fillText('/ ' + d.total, W / 2, cy + 132);

    // rank pill
    y = 1030;
    if (d.rank) {
        ctx.font = font(40);
        const w = ctx.measureText(d.rank).width + 96;
        const x0 = W / 2 - w / 2;
        // polished gold: a bright band across the middle, deeper gold at the edges, a fine light rim
        const goldFill = ctx.createLinearGradient(0, y - 52, 0, y + 24);
        goldFill.addColorStop(0, '#fff3b0');
        goldFill.addColorStop(0.45, '#f4c542');
        goldFill.addColorStop(0.55, '#e0a81e');
        goldFill.addColorStop(1, '#b07a12');
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,0.35)';
        ctx.shadowBlur = 18;
        ctx.shadowOffsetY = 6;
        ctx.fillStyle = goldFill;
        ctx.beginPath();
        ctx.roundRect(x0, y - 52, w, 76, 38);
        ctx.fill();
        ctx.restore();
        ctx.strokeStyle = 'rgba(255,248,214,0.9)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.roundRect(x0 + 3, y - 49, w - 6, 70, 35);
        ctx.stroke();
        ctx.fillStyle = '#3d2a00';
        ctx.fillText(d.rank, W / 2, y);
        y += 40;
    }

    // student
    ctx.fillStyle = C.white;
    ctx.font = font(56);
    ctx.fillText(d.name, W / 2, y + 70);
    ctx.font = font(36, 'normal');
    ctx.globalAlpha = 0.9;
    ctx.fillText(d.roll, W / 2, y + 120);

    // footer
    ctx.font = font(30, 'normal');
    ctx.globalAlpha = 0.8;
    ctx.fillText(d.footer, W / 2, H - 112);
    ctx.globalAlpha = 1;
    ctx.fillStyle = C.goldLight;
    ctx.font = font(34);
    ctx.fillText(d.site, W / 2, H - 70);
    ctx.globalAlpha = 1;

    return await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));
}
