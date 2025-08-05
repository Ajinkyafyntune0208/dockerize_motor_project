export const typeRename = (type) =>
  type === "bike" ? "Two Wheeler" : "Car Insurance";

export const ownershipRename = (ownership) =>
  ownership === "I" ? "Individual" : ownership === "C" ? "Company" : ownership;

export const prevPolicyType = (prevPolicy) => (prevPolicy ? prevPolicy : "N/A");
