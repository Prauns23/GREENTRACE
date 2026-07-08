// Three.js tree viewer + AR (WebXR)

let scene, camera, renderer, controls;
let treeGroup = null;
let selectedTreeId = null;
let arSession = null;
let arRenderer = null;
let arCamera = null;
let hitTestSource = null;
let localSpace = null;
let arTreeGroup = null;
let isArMode = false;
let currentQrTreeId = null;
let qrCodeInstance = null;

function initThree() {
  const container = document.getElementById("threeContainer");
  const box = document.getElementById("simulationBox");

  scene = new THREE.Scene();
  scene.background = new THREE.Color(0xf0fdf4);
  scene.fog = new THREE.Fog(0xf0fdf4, 60, 300); // fixed hex

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

  // Grid - increased size for better scale reference
  const gridSize = 500;
  const gridDivisions = 40;
  const gridHelper = new THREE.GridHelper(
    gridSize,
    gridDivisions,
    0x888888,
    0xcccccc,
  );
  gridHelper.position.y = 0;
  scene.add(gridHelper);

  animate();

  window.addEventListener("resize", onResize);

  document.getElementById("placeholderContent").style.display = "block";
}

function showQrModal(treeId, treeName) {
  const modal = document.getElementById("qrModal");
  const container = document.getElementById("qrCodeContainer");
  const title = document.getElementById("qrModalTitle");
  const description = document.getElementById("qrDescription");

  // Clear previous QR code
  container.innerHTML = "";
  if (qrCodeInstance) {
    qrCodeInstance.clear();
    qrCodeInstance = null;
  }

  // Set title
  title.textContent = `${treeName} QR Code`;

  // Description
  description.innerHTML = `Print or share this QR code. Scan it with the AR Camera to instantly view the <strong>${treeName}</strong> tree at full scale.`;

  // Generate new QR code (using tree ID as content)
  qrCodeInstance = new QRCode(container, {
    text: treeId.toString(),
    width: 200,
    height: 200,
    colorDark: "#1a1a1a",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H,
  });

  // Store current tree ID for download
  currentQrTreeId = treeId;

  // Show modal
  modal.style.display = "flex";
}

function closeQrModal() {
  document.getElementById("qrModal").style.display = "none";
  if (qrCodeInstance) {
    qrCodeInstance.clear();
    qrCodeInstance = null;
  }
}

function downloadQrCode() {
  if (!currentQrTreeId) return;

  const canvas = document.querySelector("#qrCodeContainer canvas");
  if (!canvas) return;

  // Create a download link
  const link = document.createElement("a");
  link.download = `tree_${currentQrTreeId}_qr.png`;
  link.href = canvas.toDataURL("image/png");
  link.click();
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

// Build tree for simulation
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

  const areaCircleGeo = new THREE.CircleGeometry(spacing, 48);
  const areaCircleMat = new THREE.MeshBasicMaterial({
    color: 0x2e7d32,
    transparent: true,
    opacity: 0.08,
    side: THREE.DoubleSide,
    depthWrite: false,
  });
  const areaCircle = new THREE.Mesh(areaCircleGeo, areaCircleMat);
  areaCircle.rotation.x = -Math.PI / 2;
  areaCircle.position.y = 0.01;
  treeGroup.add(areaCircle);

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
  human.position.set(3, 0, 0);
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
        <h3><i class="fa-solid fa-expand" style="font-size: 1.2rem; margin-right: 8px;"></i> ${species.name} — ${height}m tall</h3>
        <p><p><span class="label">Plant spacing:</span> ${spacing.toFixed(1)}m</p></p>
        <p class="human-note">Human: 1.63m</p>
    </div>
`;
  overlay.classList.remove("hidden");
}

// Build tree for AR
function buildTreeForAR(species) {
  const group = new THREE.Group();
  const height = species.mature_height || 15;
  const trunkRadius = (species.trunk_diameter || 0.5) / 2;
  const canopyRadius = (species.canopy_diameter || 5) / 2;
  const spacing = species.planting_spacing || species.canopy_diameter * 1.5 || 5;
  const leafColor = new THREE.Color(species.leaf_color || "#2e7d32");
  const trunkColor = new THREE.Color(species.trunk_color || "#8d6e63");

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
  group.add(trunk);

  // Crown
  const crownGeo = new THREE.SphereGeometry(canopyRadius, 16, 12);
  const crownMat = new THREE.MeshStandardMaterial({
    color: leafColor,
    roughness: 0.7,
  });
  const crown = new THREE.Mesh(crownGeo, crownMat);
  crown.castShadow = true;
  crown.position.y = height * 0.7 + canopyRadius * 0.3;
  group.add(crown);

  // Planting spacing circle and ring
  const areaCircleGeo = new THREE.CircleGeometry(spacing, 48);
  const areaCircleMat = new THREE.MeshBasicMaterial({
    color: 0x2e7d32,
    transparent: true,
    opacity: 0.08,
    side: THREE.DoubleSide,
    depthWrite: false,
  });
  const areaCircle = new THREE.Mesh(areaCircleGeo, areaCircleMat);
  areaCircle.rotation.x = -Math.PI / 2;
  areaCircle.position.y = 0.01;
  group.add(areaCircle);

  // Ring outline (line loop)
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
  group.add(line);

  // Human reference
  const human = createHumanFigure(1.63);
  human.position.set(trunkRadius + 0.6, 0, 0);
  group.add(human);

  return group;
}

// AR Functions
async function startAR() {
  console.log("startAR called");
  if (!selectedTreeId) {
    alert("Please select a tree first.");
    return;
  }
  const species = treeData.find((t) => t.id === selectedTreeId);
  if (!species) {
    alert("Tree data not found.");
    return;
  }

  // Check WebXR support
  if (
    !navigator.xr ||
    !(await navigator.xr.isSessionSupported("immersive-ar"))
  ) {
    alert("AR not supported. Please use Chrome on Android.");
    return;
  }

  // Make simulationBox fullscreen
  const container = document.getElementById("simulationBox");
  window._originalContainerStyle = container.getAttribute("style") || "";
  container.style.position = "fixed";
  container.style.top = "0";
  container.style.left = "0";
  container.style.width = "100vw";
  container.style.height = "100vh";
  container.style.zIndex = "9999";
  container.style.background = "transparent";
  container.style.overflow = "hidden";

  // Hide simulation, show AR overlay
  document.getElementById("threeContainer").style.display = "none";
  document.getElementById("arOverlay").style.display = "block";
  document.getElementById("placeholderContent").style.display = "none";

  // Create AR renderer (transparent background for camera feed)
  arRenderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  arRenderer.setPixelRatio(window.devicePixelRatio);
  arRenderer.setSize(window.innerWidth, window.innerHeight);
  arRenderer.xr.enabled = true;

  // Keep the AR canvas behind the HTML overlay
  arRenderer.domElement.style.position = "absolute";
  arRenderer.domElement.style.top = "0";
  arRenderer.domElement.style.left = "0";
  arRenderer.domElement.style.zIndex = "0";
  arRenderer.domElement.style.pointerEvents = "none";

  container.appendChild(arRenderer.domElement);

  // AR scene
  const arScene = new THREE.Scene();
  arScene.background = null;
  const ambient = new THREE.AmbientLight(0xffffff, 0.6);
  arScene.add(ambient);
  const dirLight = new THREE.DirectionalLight(0xffffff, 1.0);
  dirLight.position.set(10, 20, 10);
  arScene.add(dirLight);

  arCamera = new THREE.PerspectiveCamera(
    70,
    window.innerWidth / window.innerHeight,
    0.01,
    100,
  );

  // Reticle
  const reticle = new THREE.Mesh(
    new THREE.RingGeometry(0.12, 0.15, 32).rotateX(-Math.PI / 2),
    new THREE.MeshBasicMaterial({ color: 0x2e7d32 }),
  );
  reticle.matrixAutoUpdate = false;
  reticle.visible = false;
  arScene.add(reticle);

  // Tree for AR
  const arTree = buildTreeForAR(species);
  arTree.visible = false;
  arScene.add(arTree);
  arTreeGroup = arTree;

  // Request AR session
  const session = await navigator.xr.requestSession("immersive-ar", {
    requiredFeatures: ["hit-test", "local-floor"],
  });
  arSession = session;

  // Hit-test source
  const viewerSpace = await session.requestReferenceSpace("viewer");
  hitTestSource = await session.requestHitTestSource({ space: viewerSpace });
  localSpace = await session.requestReferenceSpace("local-floor");

  await arRenderer.xr.setSession(session);
  isArMode = true;

  // On tap: place tree
  session.addEventListener("select", () => {
    if (reticle.visible && arTreeGroup) {
      arTreeGroup.position.setFromMatrixPosition(reticle.matrix);
      arTreeGroup.visible = true;
      reticle.visible = false;
      document.getElementById("arHint").style.display = "none";
    }
  });

  session.addEventListener("end", () => {
    cleanupAR();
  });

  // AR animation loop
  function arAnimate(time, frame) {
    if (frame && hitTestSource && localSpace) {
      const hits = frame.getHitTestResults(hitTestSource);
      if (hits.length) {
        const pose = hits[0].getPose(localSpace);
        reticle.visible = true;
        reticle.matrix.fromArray(pose.transform.matrix);
      } else {
        reticle.visible = false;
      }
    }
    arRenderer.render(arScene, arCamera);
  }
  arRenderer.setAnimationLoop(arAnimate);

  // Resize handler
  const arResize = () => {
    const w = window.innerWidth;
    const h = window.innerHeight;
    arRenderer.setSize(w, h);
    arCamera.aspect = w / h;
    arCamera.updateProjectionMatrix();
  };
  window.addEventListener("resize", arResize);
  window._arResize = arResize;

  // Update UI – ensure the button is clickable
  document.getElementById("startArBtn").textContent = "AR Active";
  document.getElementById("startArBtn").disabled = true;
  document.getElementById("exitArBtnWrapper").style.display = "block";
  document.getElementById("arHint").style.display = "block";
}

function cleanupAR() {
  isArMode = false;
  if (arRenderer) {
    arRenderer.setAnimationLoop(null);
    arRenderer.domElement.remove();
    arRenderer = null;
  }
  if (arSession) {
    arSession = null;
  }
  hitTestSource = null;
  localSpace = null;
  arTreeGroup = null;

  // Restore simulationBox style
  const container = document.getElementById("simulationBox");
  if (window._originalContainerStyle !== undefined) {
    container.setAttribute("style", window._originalContainerStyle);
  } else {
    container.removeAttribute("style");
  }

  // Show simulation again
  document.getElementById("threeContainer").style.display = "block";
  document.getElementById("arOverlay").style.display = "none";
  document.getElementById("placeholderContent").style.display = "block";
  document.getElementById("exitArBtnWrapper").style.display = "none";
  document.getElementById("startArBtn").textContent = "Start AR";
  document.getElementById("startArBtn").disabled = false;

  // Remove resize listener
  if (window._arResize) {
    window.removeEventListener("resize", window._arResize);
    window._arResize = null;
  }

  // Rebuild the tree in simulation
  const species = treeData.find((t) => t.id === selectedTreeId);
  if (species) buildTree(species);

  // Reload the page to fully reset the renderer and controls
  window.location.reload();
}

async function exitAR() {
  console.log("exitAR called");
  if (arSession) {
    await arSession.end();
  }
  cleanupAR();
}

// Event Listeners
document.addEventListener("DOMContentLoaded", function () {
  initThree();

  document.getElementById("treeInfoOverlay").classList.add("hidden");

  // Tree cards
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
        document.getElementById("selectedTreeId").value = id;
        buildTree(species);
      }
    });
  });

  // QR button on each tree card
  document.querySelectorAll(".qr-btn").forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.stopPropagation(); // prevent triggering the card click
      const card = this.closest(".tree-card");
      const treeId = parseInt(card.dataset.tree);
      const species = treeData.find((t) => t.id === treeId);
      if (species) {
        showQrModal(treeId, species.name);
      }
    });
  });

  // Close modal buttons
  document
    .getElementById("closeQrModalBtn")
    .addEventListener("click", closeQrModal);
  document.getElementById("qrModal").addEventListener("click", function (e) {
    if (e.target === this) closeQrModal();
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeQrModal();
  });

  // Download button
  document
    .getElementById("downloadQrBtn")
    .addEventListener("click", downloadQrCode);

  // Auto‑select Apitong
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
      document.getElementById("selectedTreeId").value = apitong.id;
      buildTree(apitong);
    } else {
      const firstCard = document.querySelector(".tree-card");
      if (firstCard) firstCard.click();
    }
  } else {
    const firstCard = document.querySelector(".tree-card");
    if (firstCard) firstCard.click();
  }

  // Scan QR (placeholder)
  document.getElementById("scanQrBtn").addEventListener("click", function () {
    alert("QR scanner will be implemented later.");
  });

  // Start AR
  document.getElementById("startArBtn").addEventListener("click", startAR);
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