import { currencyFormater } from "utils";

export const addonObject = (quote, addonList, totalAddon) => {
  return {
    title: quote?.applicableAddons.includes("imt23")
      ? "Addons & Covers"
      : "Addons",
    list: addonList,
    total: {
      "Total Addon Premium (D)": `₹ ${currencyFormater(totalAddon)}`,
    },
  };
};
