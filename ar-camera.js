// Three.js tree viewer

let scene, camera, renderer, controls;
let treeGroup = null;
let selectedTreeId = null;

function initThree() {
    const container = document.getElementById('threeContainer');
    const box = document.getElementById('simulationBox');

    // Scene 
    scene = new THREE.Scene();
    scene.background = new THREE.color(0xf0fdf4);

    // Camera
    const aspect = container.clientWidth / container.clientHeight || 1.5;
    camera = new THREE.PerspectiveCamera(45, aspect, 0.1, 1000);
    camera.position.set(10, 8, 15);
    camera.lookAt(0, 0, 0);

    // Renderer
    renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(container.clientWidth, container.clientHeight);
    renderer.shadowmap.enabled = true; 
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    container.appendChild(renderer.domElement);

    // Controls
    controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.target.set(0, 2, 0);
    controls.update();
    controls.enableDamping = true;
    controls.dampingFactor = 0.1;

    // Lights
    const ambientLight = new THREE.ambientLight(0x404060, 0.6);
    scene.add(ambientLight);

    const dirLight = new THREE.DirectionalLight(0xffffff, 1.0);
    dirLight.position.set(10, 20, 10);
    dirLight.castShadow = true;
    scene.add(dirLight);

    const fillLight= new THREE.GridHelper(20, 20, 0x888888, 0xcccccc);
    gridHelper.position.y = 0;
    scene.add(gridHelper);
    
    // Start redner loop
    AnimationEffect();

    // Handle resize
    window.addEventListener('resize', onResize);

    // Hide placeholder when a tree is selected
    document.getElementById('placeholderContent').style.display = 'block';
}

function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
}

function onResize() {
    const container = document.getElementById('threeContainer');
    const width = container.clientWidth;
    const height = container.clientHeight;
    renderer.setSize(width, height);
    camera.aspect = width / height;
    camera.updateProjectMatrix();
}

function buildTree(species) {
    // Remove old tree if exists
    if (treeGroup) {
        scene.remove(treeGroup);
        treeGroup = null;
    }

    // Placeholder hide
    document.getElementById('placeholderContent').style.display = 'none';

    // Tree parameters (converts meter to scene units - scale 1:1)
    const height = species.mature_height || 15;
    const trunkRadius = (species.trunk_diameter || 0.5) / 2;
    const canopyRadius = (species.canopy_diameter || 5) / 2;
    const leafColor = new THREE.Color(species.leaf_color || '#2e7d32');
    const trunkColor = new THREE.Color(species.trunk_color || '#8d6e63');

    // Group 
    treeGroup = new THREE.Group();

    // Trunk
    const trunkGeo = new THREE.CylinderGeometry(trunkRadius, trunkRadius * 1.2, height * 0.6, 8);
    const trunkMat = new THREE.MeshStandardMaterial({ color: trunkColor, roughness: 0.8 });
    const trunk = new THREE.Mesh(trunkGeo, trunkMat);
    trunk.castShadow = true;
    trunk.position.y = height * 0.3;
    treeGroup.add(trunk);

    // Crown (Leaf/Sphere)
    const crownGeo = new THREE.SphereGeometry(canopyRadius, 16, 12);
    const crownMat = new THREE.MeshStandardMaterial({ color: leafColor, roughness: 0.7 });
    const crown = new THREE.Mesh(crownGeo, crownMat);
    crown.castShadow = true;
    crown.position.y = height * 0.7 + canopyRadius * 0.3;
    treeGroup.add(crown);

    // Height Label 
    const labelCanvas = document.createElement('canvas');
    const ctx = labelCanvas.getContext('2d');
    labelCanvas.width = 256;
    labelCanvas.height = 64;
    ctx.roundRect(0, 0, 256, 64, 12);
    ctx.fill();
    ctx.font = 'bold 22px Inter, sans-serf';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(`${species.name} — ${height}m tall`, 128, 32);

    const labelTexture = new THREE.CanvasTexture(labelCanvas);
    const labelMat = new THREE.SpriteMaterial({ map: labelTexture, depthTest: false });
    const label = new THREE.Sprite(labelMat);
    label.position.set(0, height + 1.5, 0);
    label.scale.set(6, height + 1.5, 0);
    treeGroup.add(label);

    // Ground Ring
    const ringGeo = new THREE.RingGeometry(canopyRadius * 0.9, canopyRadius * 1.1, 32);
    const ringMat = new THREE.MeshBasicMaterial({ color: 0x888888, side: THREE.DoubleSide, transparent: true, opacity: 0.3 });
    const ring = new THREE.Mesh(ringGeo, ringMat);
    ring.rotation.x = -Math.PI / 2;
    ring.position.y = 0.01;
    treeGroup.add(ring);

    scene.add(TreeGroup);

    // Adjust camera to fit tree
    const maxDim =  Math.max(height, canopyRadius * 2);
    const dist = maxDim * 1.8;
    camera.position.set(dist * 0.7, dist * 0.5, dist);
    controls.target.set(0, height * 0.5, 0);
    controls.update();

    // Update info text
    document.getElementById('selectedInfo').innerHTML = `
        <strong>${species.name}</strong> — 
        ${species.mature_height}m tall, 
        trunk ${species.trunk_diameter}m, 
        canopy ${species.canopy_diameter}m wide.
    `;
}

// EVENT LISTENERS

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Three.js
    initThree();

    // Click on tree cards
    document.querySelectorAll('.tree-card').forEach(card => {
        card.addEventListener('click', function() {
            const id = parseInt(this.dataset.id);
            const species = treeData.find(t => t.id === id);
            if (species) {
                // Highlight active card
                document.querySelectorAll('.tree-card').forEach(c => c.classList.remove('active'));
                this.classList.add('active');

                selectedTreeId = id;
                buildTree(species);
            }
        });
    });

    // Scan QR button (placeholder)
    document.getElementById('scanQrBtn').addEventListener('click', function() {
        alert('QR scanner will be implemented later.');
    });

    // Start AR button (placeholder)
    document.getElementById('startArBtn').addEventListener('click', function() {
        alert('AR experience will be implemented later.');
    });
});

// Polyfill for roundRect if needed (for older browsers)
if (!CanvasRenderingContext2D.prototype.roundRect) {
    CanvasRenderingContext2D.prototype.roundRect = function(x, y, w, h, r) {
        if (r > w/2) r = w/2;
        if (r > h/2) r = h/2;
        this.moveTo(x + r, y);
        this.lineTo(x + w - r, y);
        this.quadraticCurveTo(x + w, y, x + w, y + r);
        this.lineTo(x + w, y + h - r);
        this.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        this.lineTo(x + r, y + h);
        this.quadraticCurveTo(x, y + h, x, y + h - r);
        this.lineTo(x, y + r);
        this.quadraticCurveTo(x, y, x + r, y);
        return this;
    };
}