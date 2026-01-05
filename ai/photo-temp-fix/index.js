/**
 * Photo Temperature Fix
 * Batch photo color temperature correction tool
 * All processing happens locally in the browser
 */

class PhotoTemperatureFix {
  constructor() {
    // Store photos
    this.photos = [];
    this.processedData = []; // Store processed blobs here
    this.currentPreviewIndex = 0;

    // UI Elements
    this.dropZone = document.getElementById("dropZone");
    this.fileInput = document.getElementById("fileInput");
    this.uploadStats = document.getElementById("uploadStats");
    this.photoCount = document.getElementById("photoCount");
    this.clearPhotosBtn = document.getElementById("clearPhotos");

    this.browseBtn = document.getElementById("browseBtn");
    this.queueSection = document.getElementById("queueSection");
    this.processSection = document.getElementById("processSection");
    this.controlPanel = document.getElementById("controlPanel");

    this.previewSection = document.getElementById("previewSection");
    this.originalCanvas = document.getElementById("originalCanvas");
    this.correctedCanvas = document.getElementById("correctedCanvas");
    this.prevPhotoBtn = document.getElementById("prevPhoto");
    this.nextPhotoBtn = document.getElementById("nextPhoto");
    this.previewIndex = document.getElementById("previewIndex");

    this.thumbnailsSection = document.getElementById("thumbnailsSection");
    this.thumbnailsGrid = document.getElementById("thumbnailsGrid");

    this.progressContainer = document.getElementById("progressContainer");
    this.progressFill = document.getElementById("progressFill");
    this.progressText = document.getElementById("progressText");

    // New Flow Elements
    this.startProcessBtn = document.getElementById("startProcessBtn");
    this.downloadOptions = document.getElementById("downloadOptions");
    this.downloadZipBtn = document.getElementById("downloadZipBtn");
    this.processInfo = document.getElementById("processInfo");
    this.resetProcessBtn = document.getElementById("resetProcessBtn");

    // Controls
    this.temperatureSlider = document.getElementById("temperatureSlider");
    this.temperatureValue = document.getElementById("temperatureValue");
    this.presetButtons = document.querySelectorAll(".preset-chip");

    this.init();
  }

  init() {
    // Download info element
    this.downloadInfo = document.getElementById("downloadInfo");

    // File handling events
    this.dropZone.addEventListener("dragover", (e) => {
      e.preventDefault();
      this.dropZone.classList.add("dragover");
    });

    this.dropZone.addEventListener("dragleave", () => {
      this.dropZone.classList.remove("dragover");
    });

    this.dropZone.addEventListener("drop", (e) => {
      e.preventDefault();
      this.dropZone.classList.remove("dragover");
      this.handleFiles(e.dataTransfer.files);
    });

    this.browseBtn.addEventListener("click", () => this.fileInput.click());
    this.fileInput.addEventListener("change", (e) =>
      this.handleFiles(e.target.files)
    );

    // Clear photos
    this.clearPhotosBtn.addEventListener("click", () => this.clearAllPhotos());

    // Controls
    this.temperatureSlider.addEventListener("input", (e) =>
      this.handleTemperatureChange(e.target.value)
    );

    // Preset buttons initialization
    this.initPresetButtons();

    // Set initial preset as active
    this.setActivePreset(-40);

    // Preview navigation
    this.prevPhotoBtn.addEventListener("click", () => this.navigatePreview(-1));
    this.nextPhotoBtn.addEventListener("click", () => this.navigatePreview(1));

    // New Process Flow Buttons
    this.startProcessBtn.addEventListener("click", () =>
      this.startProcessing()
    );
    this.downloadZipBtn.addEventListener("click", () => this.downloadZip());
    this.resetProcessBtn.addEventListener("click", () => this.resetProcess());

    // Share button for mobile (Web Share API - requires HTTPS)
    this.shareBtn = document.getElementById("shareBtn");
    if (this.shareBtn) {
      this.shareBtn.addEventListener("click", () => this.sharePhotos());
      // Only show on mobile devices where Web Share API with files might work
      const isMobile = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent);
      const isSecure =
        window.location.protocol === "https:" ||
        window.location.hostname === "localhost";
      if (isMobile && navigator.share && isSecure) {
        this.shareBtn.hidden = false;
      }
    }

    // Theme toggle
    this.initTheme();
  }

  initPresetButtons() {
    this.presetButtons.forEach((btn) => {
      btn.addEventListener("click", () => this.applyPreset(btn));
    });
  }

  initTheme() {
    const themeToggle = document.getElementById("themeToggle");
    const themeIcon = document.getElementById("themeIcon");
    const html = document.documentElement;

    // Load saved theme or use system preference
    const savedTheme = localStorage.getItem("photo-tuner-theme");
    const prefersDark = window.matchMedia(
      "(prefers-color-scheme: dark)"
    ).matches;
    const initialTheme = savedTheme || (prefersDark ? "dark" : "light");

    html.setAttribute("data-theme", initialTheme);
    themeIcon.textContent =
      initialTheme === "dark" ? "light_mode" : "dark_mode";

    themeToggle.addEventListener("click", () => {
      const currentTheme = html.getAttribute("data-theme");
      const newTheme = currentTheme === "dark" ? "light" : "dark";

      html.setAttribute("data-theme", newTheme);
      localStorage.setItem("photo-tuner-theme", newTheme);
      themeIcon.textContent = newTheme === "dark" ? "light_mode" : "dark_mode";
    });
  }

  applyPreset(btn) {
    const value = btn.dataset.value;

    if (value === "custom") {
      // Just activate custom button, don't change slider
      this.setActivePresetByName("custom");
    } else {
      const numValue = parseInt(value);
      this.temperatureSlider.value = numValue;
      this.temperatureValue.textContent =
        numValue > 0 ? `+${numValue}` : numValue;
      this.setActivePreset(numValue);
      this.updatePreview();
    }
  }

  setActivePreset(value) {
    document.querySelectorAll(".preset-chip").forEach((btn) => {
      const btnValue = btn.dataset.value;
      btn.classList.toggle("active", parseInt(btnValue) === value);
    });
  }

  setActivePresetByName(name) {
    document.querySelectorAll(".preset-btn").forEach((btn) => {
      btn.classList.toggle("active", btn.dataset.name === name);
    });
  }

  handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    this.dropZone.classList.add("drag-over");
  }

  handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    this.dropZone.classList.remove("drag-over");
  }

  handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    this.dropZone.classList.remove("drag-over");

    const files = e.dataTransfer.files;
    this.handleFiles(files);
  }

  async handleFiles(files) {
    const imageFiles = Array.from(files).filter((file) =>
      file.type.startsWith("image/")
    );

    if (imageFiles.length === 0) return;

    // Warn if adding many photos (UX request)
    if (this.photos.length + imageFiles.length > 50) {
      alert(
        "Uwaga: Przetwarzanie dużej ilości zdjęć może chwilę potrwać. Prosimy o cierpliwość."
      );
    }

    // Limit to 1000 photos
    if (this.photos.length + imageFiles.length > 1000) {
      alert("Możesz dodać maksymalnie 1000 zdjęć.");
      return;
    }

    // Process each file
    for (const file of imageFiles) {
      await this.addPhoto(file);
    }

    this.updateUI();
  }

  async addPhoto(file) {
    return new Promise((resolve) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = new Image();
        img.onload = () => {
          // Create small thumbnail for grid display (memory optimization)
          const thumbCanvas = document.createElement("canvas");
          const thumbSize = 150;
          const scale = Math.min(thumbSize / img.width, thumbSize / img.height);
          thumbCanvas.width = Math.round(img.width * scale);
          thumbCanvas.height = Math.round(img.height * scale);
          const thumbCtx = thumbCanvas.getContext("2d");
          thumbCtx.drawImage(img, 0, 0, thumbCanvas.width, thumbCanvas.height);
          const thumbnailUrl = thumbCanvas.toDataURL("image/jpeg", 0.7);

          this.photos.push({
            file: file,
            name: file.name,
            type: file.type,
            image: img,
            dataUrl: e.target.result,
            thumbnailUrl: thumbnailUrl, // Small thumbnail for grid
          });
          resolve();
        };
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);
    });
  }

  updateUI() {
    const hasPhotos = this.photos.length > 0;

    // Show/hide sections
    this.uploadStats.hidden = !hasPhotos;
    this.controlPanel.hidden = !hasPhotos;
    this.previewSection.hidden = !hasPhotos;
    this.thumbnailsSection.hidden = !hasPhotos;
    this.processSection.hidden = !hasPhotos;

    // Update count
    this.photoCount.textContent = this.photos.length;

    // Update download info
    if (this.downloadInfo) {
      this.downloadInfo.textContent = `${this.photos.length} plików`;
    }

    // Update thumbnails
    this.renderThumbnails();

    // Update preview
    if (hasPhotos) {
      this.updatePreview();
      this.updatePreviewNav();
    }
  }

  renderThumbnails() {
    this.thumbnailsGrid.innerHTML = "";

    this.photos.forEach((photo, index) => {
      const thumbnail = document.createElement("div");
      thumbnail.className =
        "thumbnail" + (index === this.currentPreviewIndex ? " active" : "");
      thumbnail.innerHTML = `
                <img src="${photo.thumbnailUrl || photo.dataUrl}" alt="${
        photo.name
      }" loading="lazy">
                <button class="remove-btn" data-index="${index}">×</button>
            `;

      thumbnail.addEventListener("click", (e) => {
        if (e.target.classList.contains("remove-btn")) {
          this.removePhoto(parseInt(e.target.dataset.index));
        } else {
          this.currentPreviewIndex = index;
          this.updatePreview();
          this.updatePreviewNav();
          this.highlightActiveThumbnail();
        }
      });

      this.thumbnailsGrid.appendChild(thumbnail);
    });
  }

  highlightActiveThumbnail() {
    const thumbnails = this.thumbnailsGrid.querySelectorAll(".thumbnail");
    thumbnails.forEach((thumb, index) => {
      thumb.classList.toggle("active", index === this.currentPreviewIndex);
    });
  }

  removePhoto(index) {
    this.photos.splice(index, 1);

    if (this.currentPreviewIndex >= this.photos.length) {
      this.currentPreviewIndex = Math.max(0, this.photos.length - 1);
    }

    this.updateUI();
  }

  clearAllPhotos() {
    this.photos = [];
    this.currentPreviewIndex = 0;
    this.updateUI();
  }

  handleTemperatureChange() {
    const value = parseInt(this.temperatureSlider.value);
    this.temperatureValue.textContent = value > 0 ? `+${value}` : value;
    this.setActivePreset(value);
    this.updatePreview();
  }

  updatePreviewNav() {
    this.prevPhotoBtn.disabled = this.currentPreviewIndex <= 0;
    this.nextPhotoBtn.disabled =
      this.currentPreviewIndex >= this.photos.length - 1;
    this.previewIndex.textContent = `${this.currentPreviewIndex + 1} / ${
      this.photos.length
    }`;
  }

  navigatePreview(direction) {
    const newIndex = this.currentPreviewIndex + direction;
    if (newIndex >= 0 && newIndex < this.photos.length) {
      this.currentPreviewIndex = newIndex;
      this.updatePreview();
      this.updatePreviewNav();
      this.highlightActiveThumbnail();
    }
  }

  updatePreview() {
    if (this.photos.length === 0) return;

    const photo = this.photos[this.currentPreviewIndex];
    const temperature = parseInt(this.temperatureSlider.value);

    // Draw original
    this.drawToCanvas(this.originalCanvas, photo.image);

    // Draw corrected
    this.drawToCanvas(this.correctedCanvas, photo.image, temperature);
  }

  drawToCanvas(canvas, image, temperatureAdjustment = 0) {
    const ctx = canvas.getContext("2d");

    // Calculate canvas size maintaining aspect ratio
    const maxWidth = 450;
    const maxHeight = 280;

    let width = image.naturalWidth;
    let height = image.naturalHeight;

    if (width > maxWidth) {
      height = (maxWidth / width) * height;
      width = maxWidth;
    }

    if (height > maxHeight) {
      width = (maxHeight / height) * width;
      height = maxHeight;
    }

    canvas.width = width;
    canvas.height = height;

    // Draw image
    ctx.drawImage(image, 0, 0, width, height);

    // Apply temperature adjustment if needed
    if (temperatureAdjustment !== 0) {
      const imageData = ctx.getImageData(0, 0, width, height);
      this.adjustTemperature(imageData, temperatureAdjustment);
      ctx.putImageData(imageData, 0, 0);
    }
  }

  /**
   * Adjust color temperature of image data
   * Negative values = cooler (remove orange/warm tint)
   * Positive values = warmer (add orange/warm tint)
   */
  adjustTemperature(imageData, adjustment) {
    const data = imageData.data;
    const factor = adjustment / 100;

    // Temperature adjustment values
    // For cooling (removing orange): reduce R, slightly reduce G, increase B
    // For warming: increase R, slightly increase G, reduce B
    const rAdjust = factor * 40; // Red channel adjustment
    const gAdjust = factor * 10; // Green channel adjustment (subtle)
    const bAdjust = -factor * 35; // Blue channel adjustment (inverse)

    for (let i = 0; i < data.length; i += 4) {
      // Apply adjustments with clamping
      data[i] = this.clamp(data[i] + rAdjust); // Red
      data[i + 1] = this.clamp(data[i + 1] + gAdjust); // Green
      data[i + 2] = this.clamp(data[i + 2] + bAdjust); // Blue
      // Alpha channel (i + 3) remains unchanged
    }
  }
  clamp(value) {
    return Math.max(0, Math.min(255, Math.round(value)));
  }

  async startProcessing() {
    if (this.photos.length === 0) return;

    const temperature = parseInt(this.temperatureSlider.value);
    const format = document.querySelector('input[name="format"]:checked').value;

    // Reset previous data
    this.processedData = [];

    // UI Setup
    this.startProcessBtn.hidden = true;
    this.progressContainer.hidden = false;
    this.processSection.classList.add("processing");
    this.controlPanel.style.pointerEvents = "none"; // Disable controls during processing

    let processed = 0;
    const total = this.photos.length;

    try {
      for (const photo of this.photos) {
        // Update progress
        this.progressFill.style.width = `${(processed / total) * 100}%`;
        this.progressText.textContent = `Przetwarzanie ${
          processed + 1
        } z ${total}...`;

        // Give UI a moment to update
        await new Promise((r) => requestAnimationFrame(r));

        // Process image
        const blob = await this.processPhoto(photo, temperature, format);

        // Get new filename
        const baseName = photo.name.replace(/\.[^/.]+$/, "");
        let extension;
        if (format === "original") {
          extension =
            photo.type === "image/png"
              ? "png"
              : photo.type === "image/webp"
              ? "webp"
              : "jpg";
        } else {
          extension = format === "png" ? "png" : "jpg";
        }
        const fileName = `${baseName}_corrected.${extension}`;

        // Store result for later use (download or share)
        this.processedData.push({
          blob: blob,
          name: fileName,
        });

        processed++;
      }

      // Processing Complete
      this.progressFill.style.width = "100%";
      this.progressText.textContent = "Gotowe!";

      setTimeout(() => {
        this.progressContainer.hidden = true;
        this.processSection.classList.remove("processing");
        this.controlPanel.style.pointerEvents = "auto";

        // Show download options
        this.downloadOptions.hidden = false;
      }, 500);
    } catch (err) {
      console.error("Processing error:", err);
      this.progressText.textContent = "Wystąpił błąd!";
      this.controlPanel.style.pointerEvents = "auto";
      setTimeout(() => {
        this.startProcessBtn.hidden = false;
        this.progressContainer.hidden = true;
      }, 2000);
    }
  }

  async downloadZip() {
    if (this.processedData.length === 0) return;

    this.downloadZipBtn.disabled = true;
    this.downloadZipBtn.innerHTML = `
        <div class="download-btn-content">
          <span class="download-text">Pakowanie...</span>
        </div>
        <span class="material-symbols-rounded">hourglass_top</span>
    `;

    const zip = new JSZip();
    const folder = zip.folder("corrected_photos");

    // Add cached files to zip
    this.processedData.forEach((item) => {
      folder.file(item.name, item.blob);
    });

    // Generate and download
    const content = await zip.generateAsync({ type: "blob" });
    const url = URL.createObjectURL(content);
    const a = document.createElement("a");
    a.href = url;
    a.download = "zdjecia_skorygowane.zip";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    // Restore button
    this.downloadZipBtn.disabled = false;
    this.downloadZipBtn.innerHTML = `
        <div class="download-btn-content">
          <span class="download-text">Pobierz ZIP</span>
          <span class="download-info">Wszystkie w jednym pliku</span>
        </div>
        <span class="material-symbols-rounded">folder_zip</span>
    `;
  }

  async sharePhotos() {
    if (this.processedData.length === 0) return;

    // Create File objects from cached blobs
    // This is instant, so we stay within the "user activation" window
    const files = this.processedData.map(
      (item) => new File([item.blob], item.name, { type: item.blob.type })
    );

    try {
      if (navigator.canShare && navigator.canShare({ files })) {
        await navigator.share({
          files: files,
          title: "Skorygowane zdjęcia",
        });
      } else {
        alert("Twoja przeglądarka nie pozwala na udostępnienie tych plików.");
      }
    } catch (err) {
      if (err.name !== "AbortError") {
        alert("Błąd udostępniania: " + err.message);
      }
    }
  }

  resetProcess() {
    this.processedData = [];
    this.downloadOptions.hidden = true;
    this.startProcessBtn.hidden = false;

    // Reset progress bar
    this.progressFill.style.width = "0%";
  }

  async processPhoto(photo, temperature, format) {
    return new Promise((resolve) => {
      // Create full-size canvas
      const canvas = document.createElement("canvas");
      const ctx = canvas.getContext("2d");

      canvas.width = photo.image.naturalWidth;
      canvas.height = photo.image.naturalHeight;

      // Draw original image at full size
      ctx.drawImage(photo.image, 0, 0);

      // Apply temperature adjustment
      if (temperature !== 0) {
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        this.adjustTemperature(imageData, temperature);
        ctx.putImageData(imageData, 0, 0);
      }

      // Determine output format
      let mimeType, quality;

      if (format === "original") {
        mimeType = photo.type;
        quality = mimeType === "image/jpeg" ? 1.0 : undefined;
      } else if (format === "png") {
        mimeType = "image/png";
        quality = undefined;
      } else {
        mimeType = "image/jpeg";
        quality = 1.0;
      }

      // Get canvas as data URL
      const outputDataUrl = canvas.toDataURL(mimeType, quality);

      // Try to preserve EXIF for JPEG images
      let finalDataUrl = outputDataUrl;

      if (
        mimeType === "image/jpeg" &&
        photo.type === "image/jpeg" &&
        typeof piexif !== "undefined"
      ) {
        try {
          // Extract EXIF from original
          const originalExif = piexif.load(photo.dataUrl);

          // Insert EXIF into new image
          finalDataUrl = piexif.insert(
            piexif.dump(originalExif),
            outputDataUrl
          );
        } catch (e) {
          // If EXIF preservation fails, use image without EXIF
          console.log("Could not preserve EXIF for:", photo.name);
        }
      }

      // Convert data URL to blob
      const byteString = atob(finalDataUrl.split(",")[1]);
      const mimeString = finalDataUrl.split(",")[0].split(":")[1].split(";")[0];
      const ab = new ArrayBuffer(byteString.length);
      const ia = new Uint8Array(ab);
      for (let i = 0; i < byteString.length; i++) {
        ia[i] = byteString.charCodeAt(i);
      }
      const blob = new Blob([ab], { type: mimeString });

      resolve(blob);
    });
  }
}

// Initialize app when DOM is ready
// Initialize app when DOM is ready or if already loaded (due to async loading)
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    new PhotoTemperatureFix();
  });
} else {
  new PhotoTemperatureFix();
}
