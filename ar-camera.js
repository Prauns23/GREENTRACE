// Three.js tree viewer

let scene, camera, renderer, controls;
let treeGroup = null;
let selectedTreeId = null;

function initThree() {
  const container = document.getElementById("threeContainer");
  const box = document.getElementById("simulationBox");

  scene = new THREE.Scene();
  scene.background = new THREE.Color(0xf0fdf4);

  const aspect = container.clientWidth / container.clientHeight || 1.5;
  camera = new THREE.PerspectiveCamera(45, aspect, 0.1, 1000);
  camera.position.set(10, 8, 15);
  camera.lookAt(0, 0, 0);

  renderer = new THREE.WebGLRenderer({ antialias: true });
  renderer.setSize(container.clientWidth, container.clientHeight);
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  container.appendChild(renderer.domElement);

  controls = new THREE.OrbitControls(camera, renderer.domElement);
  controls.target.set(0, 2, 0);
  controls.update();
  controls.enableDamping = true;
  controls.dampingFactor = 0.1;

  // Lights
  const ambientLight = new THREE.AmbientLight(0x404060, 0.6);
  scene.add(ambientLight);

  const dirLight = new THREE.DirectionalLight(0xffffff, 1.0);
  dirLight.position.set(10, 20, 10);
  dirLight.castShadow = true;
  scene.add(dirLight);

  const fillLight = new THREE.DirectionalLight(0x8888ff, 0.3);
  fillLight.position.set(-10, 5, -10);
  scene.add(fillLight);

  // Grid
  const gridHelper = new THREE.GridHelper(20, 20, 0x888888, 0xcccccc);
  gridHelper.position.y = 0;
  scene.add(gridHelper);

  animate();

  window.addEventListener("resize", onResize);

  document.getElementById("placeholderContent").style.display = "block";
}

function animate() {
  requestAnimationFrame(animate);
  controls.update();
  renderer.render(scene, camera);
}

function onResize() {
  const container = document.getElementById("threeContainer");
  const width = container.clientWidth;
  const height = container.clientHeight;
  renderer.setSize(width, height);
  camera.aspect = width / height;
  camera.updateProjectionMatrix();
}

// HUMAN FIGURE (1.63m = average Filipino height)
function createHumanFigure(height = 1.63) {
  const group = new THREE.Group();

  // Body (cylinder)
  const bodyHeight = height * 0.6;
  const bodyRadius = 0.15;
  const bodyGeo = new THREE.CylinderGeometry(
    bodyRadius * 0.8,
    bodyRadius * 1.2,
    bodyHeight,
    8,
  );
  const bodyMat = new THREE.MeshStandardMaterial({
    color: 0x2e7d32,
    roughness: 0.7,
  });
  const body = new THREE.Mesh(bodyGeo, bodyMat);
  body.castShadow = true;
  body.position.y = bodyHeight / 2 + height * 0.1;
  group.add(body);

  // Head (sphere)
  const headRadius = 0.12;
  const headGeo = new THREE.SphereGeometry(headRadius, 8, 8);
  const headMat = new THREE.MeshStandardMaterial({
    color: 0xf5d0a9,
    roughness: 0.5,
  });
  const head = new THREE.Mesh(headGeo, headMat);
  head.castShadow = true;
  head.position.y = bodyHeight + height * 0.1 + headRadius;
  group.add(head);

  // Arms (simple cylinders, optional)
  const armLength = height * 0.4;
  const armRadius = 0.04;
  const armMat = new THREE.MeshStandardMaterial({
    color: 0xf5d0a9,
    roughness: 0.7,
  });

  // Left arm
  const leftArmGeo = new THREE.CylinderGeometry(
    armRadius,
    armRadius,
    armLength,
    6,
  );
  const leftArm = new THREE.Mesh(leftArmGeo, armMat);
  leftArm.castShadow = true;
  leftArm.position.set(-bodyRadius * 1.2, bodyHeight * 0.7 + height * 0.1, 0);
  leftArm.rotation.z = -0.3;
  leftArm.rotation.x = 0.2;
  group.add(leftArm);

  // Right arm
  const rightArmGeo = new THREE.CylinderGeometry(
    armRadius,
    armRadius,
    armLength,
    6,
  );
  const rightArm = new THREE.Mesh(rightArmGeo, armMat);
  rightArm.castShadow = true;
  rightArm.position.set(bodyRadius * 1.2, bodyHeight * 0.7 + height * 0.1, 0);
  rightArm.rotation.z = 0.3;
  rightArm.rotation.x = -0.2;
  group.add(rightArm);

  // Legs
  const legLength = height * 0.35;
  const legRadius = 0.06;
  const legMat = new THREE.MeshStandardMaterial({
    color: 0x2c3e50,
    roughness: 0.8,
  });

  // Left leg
  const leftLegGeo = new THREE.CylinderGeometry(
    legRadius,
    legRadius * 1.2,
    legLength,
    6,
  );
  const leftLeg = new THREE.Mesh(leftLegGeo, legMat);
  leftLeg.castShadow = true;
  leftLeg.position.set(-bodyRadius * 0.6, legLength / 2, 0);
  group.add(leftLeg);

  // Right leg
  const rightLegGeo = new THREE.CylinderGeometry(
    legRadius,
    legRadius * 1.2,
    legLength,
    6,
  );
  const rightLeg = new THREE.Mesh(rightLegGeo, legMat);
  rightLeg.castShadow = true;
  rightLeg.position.set(bodyRadius * 0.6, legLength / 2, 0);
  group.add(rightLeg);

  return group;
}

function buildTree(species) {
  if (treeGroup) {
    scene.remove(treeGroup);
    treeGroup = null;
  }

  document.getElementById("placeholderContent").style.display = "none";

  const height = species.mature_height || 15;
  const trunkRadius = (species.trunk_diameter || 0.5) / 2;
  const canopyRadius = (species.canopy_diameter || 5) / 2;
  const spacing =
    species.planting_spacing || species.canopy_diameter * 1.5 || 5;
  const leafColor = new THREE.Color(species.leaf_color || "#2e7d32");
  const trunkColor = new THREE.Color(species.trunk_color || "#8d6e63");

  treeGroup = new THREE.Group();

  // TREE 
  // Trunk
  const trunkGeo = new THREE.CylinderGeometry(
    trunkRadius,
    trunkRadius * 1.2,
    height * 0.6,
    8,
  );
  const trunkMat = new THREE.MeshStandardMaterial({
    color: trunkColor,
    roughness: 0.8,
  });
  const trunk = new THREE.Mesh(trunkGeo, trunkMat);
  trunk.castShadow = true;
  trunk.position.y = height * 0.3;
  treeGroup.add(trunk);

  // Crown
  const crownGeo = new THREE.SphereGeometry(canopyRadius, 16, 12);
  const crownMat = new THREE.MeshStandardMaterial({
    color: leafColor,
    roughness: 0.7,
  });
  const crown = new THREE.Mesh(crownGeo, crownMat);
  crown.castShadow = true;
  crown.position.y = height * 0.7 + canopyRadius * 0.3;
  treeGroup.add(crown);

  // Height label
  const labelCanvas = document.createElement("canvas");
  const ctx = labelCanvas.getContext("2d");
  labelCanvas.width = 256;
  labelCanvas.height = 64;
  ctx.fillStyle = "rgba(0,0,0,0.6)";
  ctx.roundRect(0, 0, 256, 64, 12);
  ctx.fill();
  ctx.font = "bold 22px Inter, sans-serif";
  ctx.fillStyle = "white";
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  ctx.fillText(`${species.name} — ${height}m tall`, 128, 32);
  const labelTexture = new THREE.CanvasTexture(labelCanvas);
  const labelMat = new THREE.SpriteMaterial({
    map: labelTexture,
    depthTest: false,
  });
  const label = new THREE.Sprite(labelMat);
  label.position.set(0, height + 1.5, 0);
  label.scale.set(6, 1.5, 1);
  treeGroup.add(label);

  // Planting spacing ring
  const ringGeo = new THREE.RingGeometry(0.1, spacing, 48);
  const ringMat = new THREE.MeshBasicMaterial({
    color: 0x2e7d32,
    side: THREE.DoubleSide,
    transparent: true,
    opacity: 0.25,
  });
  const ring = new THREE.Mesh(ringGeo, ringMat);
  ring.rotation.x = -Math.PI / 2;
  ring.position.y = 0.02;
  treeGroup.add(ring);

  const points = [];
  const segments = 48;
  for (let i = 0; i <= segments; i++) {
    const angle = (i / segments) * Math.PI * 2;
    points.push(
      new THREE.Vector3(
        Math.cos(angle) * spacing,
        0,
        Math.sin(angle) * spacing,
      ),
    );
  }
  const lineGeo = new THREE.BufferGeometry().setFromPoints(points);
  const lineMat = new THREE.LineBasicMaterial({
    color: 0x2e7d32,
    transparent: true,
    opacity: 0.7,
  });
  const line = new THREE.Line(lineGeo, lineMat);
  line.position.y = 0.03;
  treeGroup.add(line);

  // Spacing label
  const spacingLabelCanvas = document.createElement("canvas");
  const sctx = spacingLabelCanvas.getContext("2d");
  spacingLabelCanvas.width = 256;
  spacingLabelCanvas.height = 48;
  sctx.fillStyle = "rgba(0,0,0,0.5)";
  sctx.roundRect(0, 0, 256, 48, 8);
  sctx.fill();
  sctx.font = "bold 16px Inter, sans-serif";
  sctx.fillStyle = "white";
  sctx.textAlign = "center";
  sctx.textBaseline = "middle";
  sctx.fillText(`Plant spacing: ${spacing.toFixed(1)}m`, 128, 24);
  const spacingLabelTexture = new THREE.CanvasTexture(spacingLabelCanvas);
  const spacingLabelMat = new THREE.SpriteMaterial({
    map: spacingLabelTexture,
    depthTest: false,
  });
  const spacingLabel = new THREE.Sprite(spacingLabelMat);
  spacingLabel.position.set(0, 0.3, 0);
  spacingLabel.scale.set(5, 1, 1);
  treeGroup.add(spacingLabel);

  // Ground ring reference
  const refRingGeo = new THREE.RingGeometry(
    canopyRadius * 0.9,
    canopyRadius * 1.1,
    32,
  );
  const refRingMat = new THREE.MeshBasicMaterial({
    color: 0x888888,
    side: THREE.DoubleSide,
    transparent: true,
    opacity: 0.2,
  });
  const refRing = new THREE.Mesh(refRingGeo, refRingMat);
  refRing.rotation.x = -Math.PI / 2;
  refRing.position.y = 0.01;
  treeGroup.add(refRing);

  // HUMAN REFERENCE (1.63m)
  const human = createHumanFigure(1.63);
  // Position the human 2.5 meters to the right of the tree (x positive)
  human.position.set(3, 0, 0);
  // Rotate slightly toward the tree
  human.rotation.y = -0.3;
  treeGroup.add(human);

  scene.add(treeGroup);

  // Adjust camera
  const maxDim = Math.max(height, spacing * 2);
  const dist = maxDim * 1.8;
  camera.position.set(dist * 0.7, dist * 0.5, dist);
  controls.target.set(0, height * 0.5, 0);
  controls.update();

  // UPDATE SELECTED INFO
  const overlay = document.getElementById("treeInfoOverlay");
  overlay.innerHTML = `
    <div class="tree-info-details">
        <h3>${species.name}</h3>
        <p><span class="label">Height:</span> ${species.mature_height}m</p>
        <p><span class="label">Trunk:</span> ${species.trunk_diameter}m</p>
        <p><span class="label">Canopy:</span> ${species.canopy_diameter}m</p>
        <p><span class="label">Plant spacing:</span> ${spacing.toFixed(1)}m</p>
        <p class="human-note"><i class="fas fa-user"></i> Human: 1.63m</p>
    </div>
`;
  overlay.classList.remove("hidden");
}

// EVENT LISTENERS
document.addEventListener("DOMContentLoaded", function () {
  initThree();

  document.getElementById("treeInfoOverlay").classList.add("hidden");

  // Click on tree cards
  document.querySelectorAll(".tree-card").forEach((card) => {
    card.addEventListener("click", function () {
      const id = parseInt(this.dataset.tree);
      const species = treeData.find((t) => t.id === id);
      if (species) {
        document
          .querySelectorAll(".tree-card")
          .forEach((c) => c.classList.remove("active"));
        this.classList.add("active");
        selectedTreeId = id;
        buildTree(species);
      }
    });
  });

  // AUTO‑SELECT APITONG
  const apitong = treeData.find((t) => t.name.toLowerCase() === "apitong");
  if (apitong) {
    const cards = document.querySelectorAll(".tree-card");
    let targetCard = null;
    cards.forEach((card) => {
      if (parseInt(card.dataset.tree) === apitong.id) {
        targetCard = card;
      }
    });
    if (targetCard) {
      targetCard.classList.add("active");
      selectedTreeId = apitong.id;
      buildTree(apitong);
    } else {
      const firstCard = document.querySelector(".tree-card");
      if (firstCard) firstCard.click();
    }
  } else {
    const firstCard = document.querySelector(".tree-card");
    if (firstCard) firstCard.click();
  }

  // Scan QR button (placeholder)
  document.getElementById("scanQrBtn").addEventListener("click", function () {
    alert("QR scanner will be implemented later.");
  });

  // Start AR button (placeholder)
  document.getElementById("startArBtn").addEventListener("click", function () {
    alert("AR experience will be implemented later.");
  });
});

// Polyfill for roundRect
if (!CanvasRenderingContext2D.prototype.roundRect) {
  CanvasRenderingContext2D.prototype.roundRect = function (x, y, w, h, r) {
    if (r > w / 2) r = w / 2;
    if (r > h / 2) r = h / 2;
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
