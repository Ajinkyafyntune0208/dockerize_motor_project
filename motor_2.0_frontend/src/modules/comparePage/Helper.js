export function comparePageWindowScrollEvent() {
  window.scrollTo(0, 0);
  const topInfos = document.querySelectorAll(".top-info1");
  const topInfoPosition = document
    .querySelector(".top-info1")
    .getBoundingClientRect().y;
  const handleBodyScroll = () => {
    if (topInfoPosition < window.scrollY)
      topInfos.forEach((topInfo) => topInfo.classList.add("planStickyHeader"));
    else
      topInfos.forEach((topInfo) =>
        topInfo.classList.remove("planStickyHeader")
      );
  };
  window.addEventListener("scroll", handleBodyScroll);
  return () => {
    window.removeEventListener("scroll", handleBodyScroll);
  };
}
